<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\bonus\AudienceBonus;
use common\models\invoice\Deposit;
use common\models\profit\Profit;
use common\models\statistics\Statistics;
use common\models\user\User;
use common\models\user\UserBalance;
use common\components\telegram\foreignSystem\PersonalBotSystem;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;

/**
 * AudienceBonusController implements actions for audience bonus management.
 */
class AudienceBonusController extends Controller
{
    /**
     * @return array
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'preview' => ['POST'],
                    'apply' => ['POST'],
                ],
            ],
        ]);
    }

    /**
     * Lists all AudienceBonus models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $dataProvider = new \yii\data\ActiveDataProvider([
            'query' => AudienceBonus::find()->orderBy(['created_at' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single AudienceBonus model.
     * @param int $id ID
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new AudienceBonus form.
     *
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        return $this->render('create');
    }

    /**
     * Preview audience and bonus amounts (AJAX)
     * @return Response
     */
    public function actionPreview()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $audienceType = Yii::$app->request->post('audience_type');
        $parameters = Yii::$app->request->post('parameters', []);
        $testUserIds = Yii::$app->request->post('test_user_ids');

        if (empty($audienceType)) {
            return ['success' => false, 'message' => 'Не указан тип аудитории'];
        }

        // Парсим тестовые ID
        $testUserIdsArray = null;
        if (!empty($testUserIds)) {
            if (is_array($testUserIds)) {
                $testUserIdsArray = array_filter(array_map('intval', $testUserIds));
            } elseif (is_string($testUserIds)) {
                $testUserIdsArray = array_filter(array_map('intval', explode(',', $testUserIds)));
            }
            if (empty($testUserIdsArray)) {
                $testUserIdsArray = null;
            }
        }

        $previewData = $this->getAudiencePreview($audienceType, $parameters, $testUserIdsArray);

        return [
            'success' => true,
            'data' => $previewData,
        ];
    }

    /**
     * Apply bonus to audience
     * @return Response
     */
    public function actionApply()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $audienceType = Yii::$app->request->post('audience_type');
        $parameters = Yii::$app->request->post('parameters', []);
        $messageTemplate = Yii::$app->request->post('message_template');
        $testUserIds = Yii::$app->request->post('test_user_ids');

        if (empty($audienceType)) {
            return ['success' => false, 'message' => 'Не указан тип аудитории'];
        }

        // Парсим тестовые ID
        $testUserIdsArray = null;
        if (!empty($testUserIds)) {
            if (is_array($testUserIds)) {
                $testUserIdsArray = array_filter(array_map('intval', $testUserIds));
            } elseif (is_string($testUserIds)) {
                $testUserIdsArray = array_filter(array_map('intval', explode(',', $testUserIds)));
            }
            if (empty($testUserIdsArray)) {
                $testUserIdsArray = null;
            }
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Получаем пользователей и суммы бонусов
            $usersData = $this->getAudienceUsers($audienceType, $parameters, $testUserIdsArray);

            $totalUsers = count($usersData);
            $totalAmount = array_sum(ArrayHelper::getColumn($usersData, 'bonus_amount'));

            // Создаем запись в истории
            $audienceBonus = new AudienceBonus();
            $audienceBonus->audience_type = (int)$audienceType;
            $audienceBonus->setParameters($parameters);
            $audienceBonus->message_template = $messageTemplate;
            $audienceBonus->setTestUserIds($testUserIdsArray);
            $audienceBonus->total_users = $totalUsers;
            $audienceBonus->total_amount = $totalAmount;
            $audienceBonus->created_by = Yii::$app->user->id;
            $audienceBonus->created_at = date('Y-m-d H:i:s');

            if (!$audienceBonus->save()) {
                $transaction->rollBack();
                return ['success' => false, 'message' => 'Ошибка сохранения: ' . implode(', ', $audienceBonus->getFirstErrors())];
            }

            // Начисляем бонусы
            $successCount = 0;
            $errorCount = 0;
            $telegramSentCount = 0;
            $telegramErrorCount = 0;

            foreach ($usersData as $userData) {
                /** @var User $user */
                $user = $userData['user'];
                $bonusAmount = $userData['bonus_amount'];

                try {
                    // Создаем Profit запись
                    $userBalance = $user->getPersonalBalance();
                    $profit = new Profit();
                    $profit->status = 1;
                    $profit->type = Profit::TYPE_AUDIENCE_BONUS;
                    $profit->amount = $bonusAmount;
                    $profit->user_balance_id = $userBalance->id;
                    $profit->comment = 'Бонус аудитории';
                    $profit->created_at = date('Y-m-d H:i:s');
                    
                    if (!$profit->save(false)) {
                        Yii::error("Failed to create profit for user {$user->id}: " . implode(', ', $profit->getFirstErrors()), __METHOD__);
                        $errorCount++;
                        continue;
                    }

                    // Пересчитываем баланс
                    $userBalance->recalculateBalance();

                    $successCount++;

                    // Отправляем сообщение в ТГ бот, если есть шаблон и chat_id
                    if (!empty($messageTemplate) && !empty($user->telegram_chat_id)) {
                        try {
                            $message = $this->formatMessage($messageTemplate, $user, $userData);
                            $personalBot = new PersonalBotSystem();
                            $personalBot->getTelegramBot()->sendMessage($user->telegram_chat_id, $message);
                            $telegramSentCount++;
                        } catch (\Exception $e) {
                            Yii::error("Failed to send telegram message to user {$user->id}: " . $e->getMessage(), __METHOD__);
                            $telegramErrorCount++;
                        }
                    }
                } catch (\Exception $e) {
                    Yii::error("Error processing user {$user->id}: " . $e->getMessage(), __METHOD__);
                    $errorCount++;
                }
            }

            $transaction->commit();

            return [
                'success' => true,
                'message' => "Начисление выполнено. Успешно: {$successCount}, Ошибок: {$errorCount}. Telegram отправлено: {$telegramSentCount}, Ошибок: {$telegramErrorCount}",
                'stats' => [
                    'total_users' => $totalUsers,
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'telegram_sent' => $telegramSentCount,
                    'telegram_errors' => $telegramErrorCount,
                ],
            ];
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error("Error applying audience bonus: " . $e->getMessage(), __METHOD__);
            return ['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()];
        }
    }

    /**
     * Получить предпросмотр аудитории
     * @param int $audienceType
     * @param array $parameters
     * @param array|null $testUserIds
     * @return array
     */
    private function getAudiencePreview($audienceType, $parameters, $testUserIds)
    {
        $usersData = $this->getAudienceUsers($audienceType, $parameters, $testUserIds);

        $totalUsers = count($usersData);
        $totalAmount = array_sum(ArrayHelper::getColumn($usersData, 'bonus_amount'));

        $previewList = [];
        foreach (array_slice($usersData, 0, 50) as $userData) {
            /** @var User $user */
            $user = $userData['user'];
            $previewList[] = [
                'id' => $user->id,
                'username' => $user->getUsername(),
                'bonus_amount' => $userData['bonus_amount'],
                'additional_info' => $userData['additional_info'] ?? [],
            ];
        }

        $previewMessage = null;
        $messageTemplate = Yii::$app->request->post('message_template');
        if (!empty($messageTemplate) && !empty($usersData)) {
            $firstUser = reset($usersData);
            $previewMessage = $this->formatMessage($messageTemplate, $firstUser['user'], $firstUser);
        }

        return [
            'total_users' => $totalUsers,
            'total_amount' => $totalAmount,
            'users' => $previewList,
            'preview_message' => $previewMessage,
            'is_test_mode' => !empty($testUserIds),
        ];
    }

    /**
     * Получить список пользователей аудитории с расчетом бонусов
     * @param int $audienceType
     * @param array $parameters
     * @param array|null $testUserIds
     * @return array Массив ['user' => User, 'bonus_amount' => float, 'additional_info' => array]
     */
    private function getAudienceUsers($audienceType, $parameters, $testUserIds)
    {
        $usersData = [];

        if ($audienceType == AudienceBonus::AUDIENCE_TYPE_DEPOSITS) {
            $usersData = $this->getDepositsAudience($parameters, $testUserIds);
        } elseif ($audienceType == AudienceBonus::AUDIENCE_TYPE_WIPES) {
            $usersData = $this->getWipesAudience($parameters, $testUserIds);
        }

        return $usersData;
    }

    /**
     * Получить аудиторию по депозитам
     * @param array $parameters
     * @param array|null $testUserIds
     * @return array
     */
    private function getDepositsAudience($parameters, $testUserIds)
    {
        // Значения по умолчанию
        $minDeposit = isset($parameters['deposit_min']) && $parameters['deposit_min'] !== '' ? (float)$parameters['deposit_min'] : 5000;
        $percent = isset($parameters['deposit_percent']) && $parameters['deposit_percent'] !== '' ? (float)$parameters['deposit_percent'] : 3;
        $minBonus = isset($parameters['deposit_min_bonus']) && $parameters['deposit_min_bonus'] !== '' ? (float)$parameters['deposit_min_bonus'] : 500;
        $round = isset($parameters['deposit_round']) && $parameters['deposit_round'] !== '' ? (float)$parameters['deposit_round'] : 100;

        $query = User::find()
            ->select(['user.id', 'SUM(deposit.amount) as total_deposit'])
            ->innerJoin('deposit', 'deposit.user_id = user.id')
            ->where(['deposit.status' => Deposit::STATUS_SUCCESS])
            ->groupBy('user.id')
            ->having(['>=', 'SUM(deposit.amount)', $minDeposit]);

        if (!empty($testUserIds) && is_array($testUserIds)) {
            $query->andWhere(['IN', 'user.id', $testUserIds]);
        }

        $results = $query->asArray()->all();

        $usersData = [];
        foreach ($results as $result) {
            $user = User::findOne($result['id']);
            if (!$user) {
                continue;
            }

            $totalDeposit = (float)$result['total_deposit'];
            
            // Расчет бонуса: total_deposit * $percent / 100, округление до $round, минимум $minBonus
            $bonusAmount = ($totalDeposit * $percent / 100);
            $bonusAmount = round($bonusAmount / $round) * $round;
            $bonusAmount = max($minBonus, $bonusAmount);

            $usersData[] = [
                'user' => $user,
                'bonus_amount' => $bonusAmount,
                'additional_info' => [
                    'total_deposit' => $totalDeposit,
                ],
            ];
        }

        return $usersData;
    }

    /**
     * Получить аудиторию по вайпам
     * @param array $parameters
     * @param array|null $testUserIds
     * @return array
     */
    private function getWipesAudience($parameters, $testUserIds)
    {
        // Значения по умолчанию
        $minWipes = isset($parameters['wipes_count']) && $parameters['wipes_count'] !== '' ? (int)$parameters['wipes_count'] : 40;
        $wipesBonus = isset($parameters['wipes_bonus']) && $parameters['wipes_bonus'] !== '' ? (float)$parameters['wipes_bonus'] : 500;

        // Сначала получаем всех пользователей
        $query = User::find()
            ->select(['user.id', 'user.steam_id', 'user.username'])
            ->where(['IS NOT', 'steam_id', null])
            ->andWhere(['!=', 'steam_id', '']);

        if (!empty($testUserIds) && is_array($testUserIds)) {
            $query->andWhere(['IN', 'user.id', $testUserIds]);
        }

        $users = $query->asArray()->all();
        
        if (empty($users)) {
            return [];
        }

        // Получаем steam_id всех пользователей
        $steamIds = array_filter(array_column($users, 'steam_id'));
        if (empty($steamIds)) {
            return [];
        }

        // Оптимизированный запрос: получаем количество вайпов для всех пользователей одним запросом
        $wipesCounts = Statistics::find()
            ->select(['steam_id', 'COUNT(DISTINCT `wipe`) as wipes_count'])
            ->where(['IN', 'steam_id', $steamIds])
            ->groupBy('steam_id')
            ->asArray()
            ->all();

        // Преобразуем в массив [steam_id => count]
        $wipesCountMap = [];
        foreach ($wipesCounts as $row) {
            $wipesCountMap[$row['steam_id']] = (int)$row['wipes_count'];
        }

        // Формируем результат
        $usersData = [];
        foreach ($users as $userData) {
            $steamId = $userData['steam_id'];
            $wipesCount = $wipesCountMap[$steamId] ?? 0;

            if ($wipesCount >= $minWipes) {
                // Загружаем модель User только для пользователей, которые прошли проверку
                $user = User::findOne($userData['id']);
                if (!$user) {
                    continue;
                }

                $usersData[] = [
                    'user' => $user,
                    'bonus_amount' => $wipesBonus,
                    'additional_info' => [
                        'wipes_count' => $wipesCount,
                    ],
                ];
            }
        }

        return $usersData;
    }

    /**
     * Форматировать сообщение с подстановкой переменных
     * @param string $template
     * @param User $user
     * @param array $userData
     * @return string
     */
    private function formatMessage($template, $user, $userData)
    {
        $replacements = [
            '{username}' => $user->getUsername(),
            '{amount}' => number_format($userData['bonus_amount'], 2, '.', ' ') . ' РУБ',
        ];

        if (isset($userData['additional_info']['total_deposit'])) {
            $replacements['{total_deposit}'] = number_format($userData['additional_info']['total_deposit'], 2, '.', ' ');
        }

        if (isset($userData['additional_info']['wipes_count'])) {
            $replacements['{wipes_count}'] = $userData['additional_info']['wipes_count'];
        }

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Finds the AudienceBonus model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return AudienceBonus the loaded model
     * @throws NotFoundHttpException if the model is not found
     */
    protected function findModel($id)
    {
        if (($model = AudienceBonus::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}

