<?php

namespace backend\controllers;

use backend\components\CrudController;
use common\components\helpers\Role;
use common\models\user\UserDrop;
use common\models\user\UserDropSearch;
use common\models\user\User;
use common\models\user\UserBalance;
use common\models\profit\Profit;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use Yii;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\helpers\ArrayHelper;

class UserDropController extends CrudController
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
        ]);
    }

    protected function _getSearchClassName()
    {
        return UserDropSearch::class;
    }


    /**
     * Изменение статуса одного предмета
     * @param int $id
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionUpdateStatus($id)
    {
        $model = $this->findModel($id);
        
        if (Yii::$app->request->isPost) {
            $status = Yii::$app->request->post('status');
            
            if ($status !== null && array_key_exists($status, UserDrop::getStatusList())) {
                $model->status = (int)$status;
                if ($model->save()) {
                    Yii::$app->session->setFlash('success', 'Статус успешно изменен!');
                } else {
                    Yii::$app->session->setFlash('error', 'Ошибка при изменении статуса: ' . implode(', ', $model->getFirstErrors()));
                }
            } else {
                Yii::$app->session->setFlash('error', 'Неверный статус!');
            }
        }
        
        return $this->redirect($this->getIndexUrl());
    }

    /**
     * Массовое изменение статусов
     * @return Response
     */
    public function actionBulkUpdateStatus()
    {
        if (Yii::$app->request->isPost) {
            $ids = Yii::$app->request->post('ids', []);
            $status = Yii::$app->request->post('status');
            
            if (empty($ids) || !is_array($ids)) {
                Yii::$app->session->setFlash('error', 'Не выбраны элементы для изменения!');
                return $this->redirect($this->getIndexUrl());
            }
            
            if ($status === null || !array_key_exists($status, UserDrop::getStatusList())) {
                Yii::$app->session->setFlash('error', 'Неверный статус!');
                return $this->redirect($this->getIndexUrl());
            }
            
            $count = UserDrop::updateAll(
                ['status' => (int)$status],
                ['id' => $ids]
            );
            
            if ($count > 0) {
                Yii::$app->session->setFlash('success', "Статус успешно изменен для {$count} элементов!");
            } else {
                Yii::$app->session->setFlash('error', 'Не удалось изменить статус!');
            }
        }
        
        return $this->redirect($this->getIndexUrl());
    }

    /**
     * Форма для массового изменения статусов по серверу и датам
     * @return string|Response
     */
    public function actionBulkChangeByServer()
    {
        $serverId = Yii::$app->request->get('server_id');
        $dateFrom = Yii::$app->request->get('date_from');
        $dateTo = Yii::$app->request->get('date_to');
        
        if (Yii::$app->request->isPost) {
            $serverId = Yii::$app->request->post('server_id');
            $dateFrom = Yii::$app->request->post('date_from');
            $dateTo = Yii::$app->request->post('date_to');
            
            if (empty($serverId) || empty($dateFrom) || empty($dateTo)) {
                Yii::$app->session->setFlash('error', 'Заполните все поля!');
            } else {
                return $this->redirect(['preview-bulk-change', 'server_id' => $serverId, 'date_from' => $dateFrom, 'date_to' => $dateTo]);
            }
        }
        
        $serversList = ArrayHelper::map(
            Servers::find()->orderBy(['name' => SORT_ASC])->all(),
            'id',
            'name'
        );
        
        return $this->render('bulk-change-by-server', [
            'serversList' => $serversList,
            'serverId' => $serverId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }
    
    /**
     * Предпросмотр списка записей для изменения
     * @return string
     */
    public function actionPreviewBulkChange()
    {
        $serverId = Yii::$app->request->get('server_id');
        $dateFrom = Yii::$app->request->get('date_from');
        $dateTo = Yii::$app->request->get('date_to');
        
        if (empty($serverId) || empty($dateFrom) || empty($dateTo)) {
            Yii::$app->session->setFlash('error', 'Не указаны параметры!');
            return $this->redirect(['bulk-change-by-server']);
        }
        
        $server = Servers::findOne($serverId);
        if (!$server) {
            Yii::$app->session->setFlash('error', 'Сервер не найден!');
            return $this->redirect(['bulk-change-by-server']);
        }
        
        // Получаем wipe сервера
        $serverWipe = $server->currentWipe();
        
        // Находим пользователей, которые играли на этом сервере с таким wipe
        $userTable = User::tableName();
        $userIds = Statistics::find()
            ->select("{$userTable}.id")
            ->distinct()
            ->innerJoin($userTable, "statistics.steam_id = {$userTable}.steam_id")
            ->where([
                'statistics.server_tag' => $server->tag,
                'statistics.wipe' => $serverWipe,
            ])
            ->andWhere(['IS NOT', "{$userTable}.id", null])
            ->column();
        
        if (empty($userIds)) {
            return $this->render('preview-bulk-change', [
                'server' => $server,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'items' => [],
                'count' => 0,
            ]);
        }
        
        // Преобразуем даты в формат для БД
        $dateFromFormatted = date('Y-m-d H:i:s', strtotime($dateFrom));
        $dateToFormatted = date('Y-m-d H:i:s', strtotime($dateTo));
        
        // Находим UserDrop записи со статусом 2 (STATUS_SENDED) в указанном диапазоне
        $items = UserDrop::find()
            ->where([
                'status' => UserDrop::STATUS_SENDED,
                'user_id' => $userIds,
            ])
            ->andWhere(['>=', 'sended_at', $dateFromFormatted])
            ->andWhere(['<=', 'sended_at', $dateToFormatted])
            ->with(['user', 'user.server', 'dropOne'])
            ->orderBy(['sended_at' => SORT_DESC])
            ->all();
        
        return $this->render('preview-bulk-change', [
            'server' => $server,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'items' => $items,
            'count' => count($items),
        ]);
    }
    
    /**
     * Подтверждение и выполнение массового изменения статусов
     * @return Response
     */
    public function actionConfirmBulkChange()
    {
        if (!Yii::$app->request->isPost) {
            Yii::$app->session->setFlash('error', 'Некорректный запрос!');
            return $this->redirect(['bulk-change-by-server']);
        }
        
        $serverId = Yii::$app->request->post('server_id');
        $dateFrom = Yii::$app->request->post('date_from');
        $dateTo = Yii::$app->request->post('date_to');
        
        if (empty($serverId) || empty($dateFrom) || empty($dateTo)) {
            Yii::$app->session->setFlash('error', 'Не указаны параметры!');
            return $this->redirect(['bulk-change-by-server']);
        }
        
        $server = Servers::findOne($serverId);
        if (!$server) {
            Yii::$app->session->setFlash('error', 'Сервер не найден!');
            return $this->redirect(['bulk-change-by-server']);
        }
        
        // Получаем wipe сервера
        $serverWipe = $server->currentWipe();
        
        // Находим пользователей, которые играли на этом сервере с таким wipe
        $userTable = User::tableName();
        $userIds = Statistics::find()
            ->select("{$userTable}.id")
            ->distinct()
            ->innerJoin($userTable, "statistics.steam_id = {$userTable}.steam_id")
            ->where([
                'statistics.server_tag' => $server->tag,
                'statistics.wipe' => $serverWipe,
            ])
            ->andWhere(['IS NOT', "{$userTable}.id", null])
            ->column();
        
        if (empty($userIds)) {
            Yii::$app->session->setFlash('error', 'Не найдено пользователей, игравших на этом сервере!');
            return $this->redirect(['bulk-change-by-server']);
        }
        
        // Преобразуем даты в формат для БД
        $dateFromFormatted = date('Y-m-d H:i:s', strtotime($dateFrom));
        $dateToFormatted = date('Y-m-d H:i:s', strtotime($dateTo));
        
        // Изменяем статус с 2 (STATUS_SENDED) на 1 (STATUS_ACTIVE)
        $count = UserDrop::updateAll(
            ['status' => UserDrop::STATUS_ACTIVE],
            [
                'and',
                ['status' => UserDrop::STATUS_SENDED],
                ['user_id' => $userIds],
                ['>=', 'sended_at', $dateFromFormatted],
                ['<=', 'sended_at', $dateToFormatted],
            ]
        );
        
        if ($count > 0) {
            Yii::$app->session->setFlash('success', "Статус успешно изменен для {$count} записей!");
        } else {
            Yii::$app->session->setFlash('warning', 'Не найдено записей для изменения!');
        }
        
        return $this->redirect(['index']);
    }

    /**
     * @param int $id
     * @return UserDrop
     * @throws NotFoundHttpException
     */
    protected function findModel($id)
    {
        $model = UserDrop::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Запись не найдена.');
        }
        return $model;
    }
    
    /**
     * Форма для начисления бонуса игрокам сервера
     * @return string|Response
     */
    public function actionBonusByServer()
    {
        $serverId = Yii::$app->request->get('server_id');
        $amount = Yii::$app->request->get('amount');
        $comment = Yii::$app->request->get('comment');
        
        if (Yii::$app->request->isPost) {
            $serverId = Yii::$app->request->post('server_id');
            $amount = Yii::$app->request->post('amount');
            $comment = Yii::$app->request->post('comment');
            
            if (empty($serverId) || empty($amount) || $amount <= 0) {
                Yii::$app->session->setFlash('error', 'Заполните все обязательные поля!');
            } else {
                return $this->redirect(['preview-bonus', 'server_id' => $serverId, 'amount' => $amount, 'comment' => $comment]);
            }
        }
        
        $serversList = ArrayHelper::map(
            Servers::find()->orderBy(['name' => SORT_ASC])->all(),
            'id',
            'name'
        );
        
        return $this->render('bonus-by-server', [
            'serversList' => $serversList,
            'serverId' => $serverId,
            'amount' => $amount,
            'comment' => $comment ?? 'Бонус за игру на сервере',
        ]);
    }
    
    /**
     * Предпросмотр списка пользователей для начисления бонуса
     * @return string
     */
    public function actionPreviewBonus()
    {
        $serverId = Yii::$app->request->get('server_id');
        $amount = Yii::$app->request->get('amount');
        $comment = Yii::$app->request->get('comment');
        
        if (empty($serverId) || empty($amount) || $amount <= 0) {
            Yii::$app->session->setFlash('error', 'Не указаны параметры!');
            return $this->redirect(['bonus-by-server']);
        }
        
        $server = Servers::findOne($serverId);
        if (!$server) {
            Yii::$app->session->setFlash('error', 'Сервер не найден!');
            return $this->redirect(['bonus-by-server']);
        }
        
        // Получаем wipe сервера
        $serverWipe = $server->currentWipe();
        
        // Находим пользователей, которые играли на этом сервере с таким wipe
        $userTable = User::tableName();
        $userIds = Statistics::find()
            ->select("{$userTable}.id")
            ->distinct()
            ->innerJoin($userTable, "statistics.steam_id = {$userTable}.steam_id")
            ->where([
                'statistics.server_tag' => $server->tag,
                'statistics.wipe' => $serverWipe,
            ])
            ->andWhere(['IS NOT', "{$userTable}.id", null])
            ->column();
        
        if (empty($userIds)) {
            return $this->render('preview-bonus', [
                'server' => $server,
                'amount' => $amount,
                'comment' => $comment,
                'users' => [],
                'count' => 0,
                'totalAmount' => 0,
            ]);
        }
        
        // Получаем пользователей
        $users = User::find()
            ->where(['id' => $userIds])
            ->orderBy(['username' => SORT_ASC])
            ->all();
        
        $totalAmount = $amount * count($users);
        
        return $this->render('preview-bonus', [
            'server' => $server,
            'amount' => $amount,
            'comment' => $comment,
            'users' => $users,
            'count' => count($users),
            'totalAmount' => $totalAmount,
        ]);
    }
    
    /**
     * Подтверждение и выполнение начисления бонуса
     * @return Response
     */
    public function actionConfirmBonus()
    {
        if (!Yii::$app->request->isPost) {
            Yii::$app->session->setFlash('error', 'Некорректный запрос!');
            return $this->redirect(['bonus-by-server']);
        }
        
        $serverId = Yii::$app->request->post('server_id');
        $amount = Yii::$app->request->post('amount');
        $comment = Yii::$app->request->post('comment');
        
        if (empty($serverId) || empty($amount) || $amount <= 0) {
            Yii::$app->session->setFlash('error', 'Не указаны параметры!');
            return $this->redirect(['bonus-by-server']);
        }
        
        $server = Servers::findOne($serverId);
        if (!$server) {
            Yii::$app->session->setFlash('error', 'Сервер не найден!');
            return $this->redirect(['bonus-by-server']);
        }
        
        // Получаем wipe сервера
        $serverWipe = $server->currentWipe();
        
        // Находим пользователей, которые играли на этом сервере с таким wipe
        $userTable = User::tableName();
        $userIds = Statistics::find()
            ->select("{$userTable}.id")
            ->distinct()
            ->innerJoin($userTable, "statistics.steam_id = {$userTable}.steam_id")
            ->where([
                'statistics.server_tag' => $server->tag,
                'statistics.wipe' => $serverWipe,
            ])
            ->andWhere(['IS NOT', "{$userTable}.id", null])
            ->column();
        
        if (empty($userIds)) {
            Yii::$app->session->setFlash('error', 'Не найдено пользователей, игравших на этом сервере!');
            return $this->redirect(['bonus-by-server']);
        }
        
        // Получаем пользователей
        $users = User::find()
            ->where(['id' => $userIds])
            ->all();
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($users as $user) {
            try {
                // Получаем или создаем баланс пользователя
                $personalBalance = $user->getPersonalBalance();
                if (empty($personalBalance) || empty($personalBalance->id)) {
                    Yii::warning("Personal balance not found for user ID: {$user->id}", __METHOD__);
                    $errorCount++;
                    continue;
                }
                
                // Создаем запись о начислении
                $profit = new Profit();
                $profit->status = 1;
                $profit->type = Profit::TYPE_BONUS;
                $profit->amount = $amount;
                $profit->user_balance_id = $personalBalance->id;
                // Комментарий: введенный пользователем текст + название сервера
                $profit->comment = trim($comment) . ' (' . $server->name . ')';
                
                if ($profit->save()) {
                    // Баланс пересчитывается автоматически через afterSave() в модели Profit
                    // Но для надежности можно явно вызвать пересчет
                    $personalBalance->recalculateBalance();
                    
                    // Отправляем уведомление в телеграм
                    if (!empty($user->telegram_chat_id)) {
                        $message = "🎁 Вам начислен бонус: <b>{$amount} РУБ</b>" . PHP_EOL . PHP_EOL;
                        $message .= "📦 Все покупки на вайп возвращены в корзину" . PHP_EOL . PHP_EOL;
                        $message .= "⚠️ Завтра 08.02 будет перевайп из-за бага на сервере #6 в 11:00 МСК" . PHP_EOL;
                        $message .= "🗺️ Карта останется прежней" . PHP_EOL;
                        $message .= "📚 Изучения стираются";
                        
                        try {
                            Yii::$app->personalBotTelegram->sendMessage($user->telegram_chat_id, $message);
                        } catch (\Exception $e) {
                            Yii::warning("Failed to send telegram message to user ID: {$user->id}, error: " . $e->getMessage(), __METHOD__);
                        }
                    }
                    
                    $successCount++;
                } else {
                    Yii::warning("Failed to save profit for user ID: {$user->id}, errors: " . json_encode($profit->getErrors()), __METHOD__);
                    $errorCount++;
                }
            } catch (\Exception $e) {
                Yii::error("Error adding bonus for user ID: {$user->id}, error: " . $e->getMessage(), __METHOD__);
                $errorCount++;
            }
        }
        
        if ($successCount > 0) {
            Yii::$app->session->setFlash('success', "Бонус успешно начислен {$successCount} пользователям на общую сумму " . ($amount * $successCount) . " руб.");
        }
        if ($errorCount > 0) {
            Yii::$app->session->setFlash('warning', "Не удалось начислить бонус {$errorCount} пользователям.");
        }
        
        return $this->redirect(['index']);
    }
}

