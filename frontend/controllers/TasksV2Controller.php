<?php

namespace frontend\controllers;

use common\components\tasks_v2\CheckResult;
use common\components\tasks_v2\TaskCheckerFactory;
use common\components\web\AuthorizedControllerTrait;
use common\controllers\WebController;
use common\models\box\Drop;
use common\models\profit\Profit;
use common\models\tasks_v2\TaskV2;
use common\models\tasks_v2\TaskV2UserCompletion;
use common\models\user\User;
use common\models\user\UserDrop;
use frontend\assets\TasksV2Asset;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Контроллер для системы заданий v2
 */
class TasksV2Controller extends WebController
{
    use AuthorizedControllerTrait;

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
     * Список заданий
     * @param string|null $type Тип фильтра (one_time, repeatable)
     * @param string|null $status Статус фильтра (available, completed, limit_reached)
     * @param string|null $sort Сортировка (popularity, reward, newest)
     * @return string
     */
    public function actionIndex($type = null, $status = null, $sort = null)
    {
        if (Yii::$app->user->isGuest) {
            throw new ForbiddenHttpException(Yii::t('common', 'Требуется авторизация'));
        }

        $this->view->params['_profile'] = true;
        $this->view->params['page'] = 'tasks-v2';

        TasksV2Asset::register($this->view);

        $user = Yii::$app->user->identity;

        // Получаем только активные задания, видимые для авторизованных пользователей
        $query = TaskV2::find()
            ->where(['is_active' => 1]);

        // Фильтр по типу
        if ($type && in_array($type, [TaskV2::TYPE_ONE_TIME, TaskV2::TYPE_REPEATABLE])) {
            $query->andWhere(['type' => $type]);
        }

        // Сортировка
        switch ($sort) {
            case 'popularity':
                $query->orderBy(['global_completed' => SORT_DESC, 'sort' => SORT_ASC]);
                break;
            case 'reward':
                $query->orderBy(['reward_amount' => SORT_DESC, 'sort' => SORT_ASC]);
                break;
            case 'newest':
                $query->orderBy(['created_at' => SORT_DESC, 'sort' => SORT_ASC]);
                break;
            default:
                $query->orderBy(['sort' => SORT_ASC, 'created_at' => SORT_DESC]);
        }

        // Загружаем ВСЕ задания для правильной сортировки
        $allModels = $query->all();
        
        // Кастомная сортировка: ежедневная награда первая, затем новые, затем выполненные
        usort($allModels, function ($a, $b) use ($user) {
            $aIsDaily = $a->type === TaskV2::TYPE_DAILY_REWARD && $a->check_type === TaskV2::CHECK_TYPE_DAILY_REWARD;
            $bIsDaily = $b->type === TaskV2::TYPE_DAILY_REWARD && $b->check_type === TaskV2::CHECK_TYPE_DAILY_REWARD;
            
            // Ежедневная награда всегда первая
            if ($aIsDaily && !$bIsDaily) {
                return -1;
            }
            if (!$aIsDaily && $bIsDaily) {
                return 1;
            }
            
            // Если обе ежедневные награды - сохраняем исходный порядок
            if ($aIsDaily && $bIsDaily) {
                return 0;
            }
            
            // Получаем статусы
            $statusA = $a->getUserStatus($user)['status'] ?? 'available';
            $statusB = $b->getUserStatus($user)['status'] ?? 'available';
            
            // Выполненные всегда внизу
            if ($statusA === 'completed' && $statusB !== 'completed') {
                return 1;
            }
            if ($statusA !== 'completed' && $statusB === 'completed') {
                return -1;
            }
            
            // Если оба выполнены или оба не выполнены - новые вверху (по created_at DESC)
            if ($statusA === 'completed' && $statusB === 'completed') {
                return strtotime($b->created_at) <=> strtotime($a->created_at);
            }
            if ($statusA !== 'completed' && $statusB !== 'completed') {
                return strtotime($b->created_at) <=> strtotime($a->created_at);
            }
            
            return 0;
        });
        
        // Вычисляем статистику по заданиям (исключая ежедневные награды)
        $totalTasks = 0;
        $completedTasks = 0;
        $totalRewards = 0; // Общее количество полученных наград (монеты + предметы)
        $totalCoins = 0; // Общее количество полученных монет
        $totalPotentialCoins = 0; // Потенциальное количество монет за все задания
        $totalPotentialRewards = 0; // Потенциальное количество наград за все задания
        
        foreach ($allModels as $task) {
            // Пропускаем ежедневные награды
            if ($task->type === TaskV2::TYPE_DAILY_REWARD && $task->check_type === TaskV2::CHECK_TYPE_DAILY_REWARD) {
                continue;
            }
            
            $totalTasks++;
            $userStatus = $task->getUserStatus($user);
            
            // Подсчитываем потенциальные награды за все задания
            if ($task->reward_type === TaskV2::REWARD_TYPE_CURRENCY && $task->reward_amount) {
                $totalPotentialCoins += $task->reward_amount;
                $totalPotentialRewards++;
            } elseif ($task->reward_type === TaskV2::REWARD_TYPE_ITEM && $task->reward_item_id) {
                $totalPotentialRewards++;
            }
            
            // Подсчитываем полученные награды только из выполненных заданий
            if ($userStatus['status'] === 'completed') {
                $completedTasks++;
                
                if ($task->reward_type === TaskV2::REWARD_TYPE_CURRENCY && $task->reward_amount) {
                    $totalCoins += $task->reward_amount;
                    $totalRewards++;
                } elseif ($task->reward_type === TaskV2::REWARD_TYPE_ITEM && $task->reward_item_id) {
                    $totalRewards++;
                }
            }
        }
        
        $completionPercent = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        // Создаем ArrayDataProvider с отсортированными данными
        $dataProvider = new \yii\data\ArrayDataProvider([
            'allModels' => $allModels,
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'user' => $user,
            'currentType' => $type,
            'currentStatus' => $status,
            'currentSort' => $sort,
            'totalTasks' => $totalTasks,
            'completedTasks' => $completedTasks,
            'completionPercent' => $completionPercent,
            'totalCoins' => $totalCoins,
            'totalRewards' => $totalRewards,
            'totalPotentialCoins' => $totalPotentialCoins,
            'totalPotentialRewards' => $totalPotentialRewards,
        ]);
    }

    /**
     * Детальная информация о задании (для модального окна)
     * @param int $id ID задания
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionDetail($id)
    {
        if (Yii::$app->user->isGuest) {
            throw new ForbiddenHttpException(Yii::t('common', 'Требуется авторизация'));
        }

        // Отключаем кэш для тестирования - используем findOne без кэша
        $task = TaskV2::find()
            ->where(['id' => $id])
            ->one();
        if (!$task || !$task->is_active) {
            throw new NotFoundHttpException(Yii::t('common', 'Задание не найдено'));
        }

        $user = Yii::$app->user->identity;
        $userStatus = $task->getUserStatus($user);

        // Для ежедневных наград добавляем информацию о текущей награде
        if ($task->type === TaskV2::TYPE_DAILY_REWARD && $task->check_type === TaskV2::CHECK_TYPE_DAILY_REWARD) {
            $userStatus['currentReward'] = $task->getCurrentDailyReward($user);
        }

        // Получаем прогресс (если нужно)
        $progress = null;
        $maxProgress = $task->max_progress; // Используем max_progress из БД
        
        try {
            $checker = TaskCheckerFactory::create($task);
            $checkResult = $checker->check($task, $user);
            $progress = $checkResult->progress;
            // maxProgress берем из БД, если не задан - используем из checker
            if ($maxProgress === null) {
                $maxProgress = $checkResult->maxProgress;
            }
        } catch (\Exception $e) {
            Yii::error('Failed to check task: ' . $e->getMessage());
        }

        // Для ежедневных наград получаем список наград
        $dailyRewardList = null;
        if ($task->type === TaskV2::TYPE_DAILY_REWARD && $task->check_type === TaskV2::CHECK_TYPE_DAILY_REWARD) {
            $dailyRewardList = $task->getDailyRewardList($user);
        }

        // Для VK задания генерируем код при открытии модалки
        $vkCode = null;
        $vkGroupId = null;
        if ($task->check_type === TaskV2::CHECK_TYPE_VK_SUBSCRIBE_GROUP) {
            $params = $task->check_params ?? [];
            $vkGroupId = $params['group_id'] ?? null;
            if ($vkGroupId) {
                $vkCode = \common\models\user\UserConfirmCode::createTypeVkGroup($user->id);
            }
        }

        return $this->renderPartial('detail', [
            'task' => $task,
            'userStatus' => $userStatus,
            'progress' => $progress,
            'maxProgress' => $maxProgress,
            'dailyRewardList' => $dailyRewardList,
            'vkCode' => $vkCode,
            'vkGroupId' => $vkGroupId,
        ]);
    }

    /**
     * Проверка и выполнение задания
     * @param int $id ID задания
     * @return Response|array
     * @throws NotFoundHttpException
     * @throws BadRequestHttpException
     */
    public function actionCheck($id)
    {
        if (Yii::$app->user->isGuest) {
            throw new ForbiddenHttpException(Yii::t('common', 'Требуется авторизация'));
        }

        Yii::$app->response->format = Response::FORMAT_JSON;

        $task = TaskV2::findOne($id);
        if (!$task || !$task->is_active) {
            return [
                'success' => false,
                'message' => Yii::t('common', 'Задание не найдено'),
            ];
        }

        $user = Yii::$app->user->identity;

        // Проверяем статус задания для пользователя
        $userStatus = $task->getUserStatus($user);
        
        if ($userStatus['status'] === 'completed' || $userStatus['status'] === 'limit_reached') {
            return [
                'success' => false,
                'message' => $userStatus['message'],
            ];
        }

        if ($userStatus['status'] === 'unavailable') {
            return [
                'success' => false,
                'message' => $userStatus['message'],
            ];
        }

        // Проверяем глобальный лимит
        if ($task->global_limit !== null && $task->global_completed >= $task->global_limit) {
            return [
                'success' => false,
                'message' => Yii::t('common', 'Задание выполнено всеми участниками'),
            ];
        }

        // Выполняем проверку через фабрику
        try {
            $checker = TaskCheckerFactory::create($task);
            $checkResult = $checker->check($task, $user);
        } catch (\Exception $e) {
            Yii::error('Failed to check task: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => Yii::t('common', 'Ошибка при проверке задания'),
            ];
        }

        if (!$checkResult->success) {
            $maxProgress = $task->max_progress ?? $checkResult->maxProgress;
            return [
                'success' => false,
                'message' => $checkResult->message,
                'progress' => $checkResult->progress,
                'maxProgress' => $maxProgress,
            ];
        }

        // Задание выполнено успешно - выдаем награду
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Создаем или обновляем запись о выполнении
            $completion = TaskV2UserCompletion::createOrUpdate($task->id, $user->id);
            
            // Увеличиваем глобальный счетчик
            $task->global_completed++;
            $task->save(false);

            // Выдаем награду
            $this->giveReward($task, $user, $checkResult);

            $transaction->commit();

            $maxProgress = $task->max_progress ?? $checkResult->maxProgress;
            return [
                'success' => true,
                'message' => Yii::t('common', 'Задание выполнено! Награда выдана.'),
                'progress' => $checkResult->progress,
                'maxProgress' => $maxProgress,
            ];
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error('Failed to complete task: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => Yii::t('common', 'Ошибка при выдаче награды'),
            ];
        }
    }

    /**
     * Выдать награду за выполнение задания
     * @param TaskV2 $task
     * @param User $user
     * @param CheckResult|null $checkResult
     * @throws \Exception
     */
    protected function giveReward(TaskV2 $task, User $user, $checkResult = null)
    {
        // Для ежедневных наград используем специальную логику
        if ($task->type === TaskV2::TYPE_DAILY_REWARD && $task->check_type === TaskV2::CHECK_TYPE_DAILY_REWARD) {
            $this->giveDailyReward($task, $user, $checkResult);
            return;
        }

        if ($task->reward_type === TaskV2::REWARD_TYPE_CURRENCY) {
            // Выдаем монеты на баланс
            $balanceType = $task->reward_currency ?? 'personal';
            
            if ($balanceType === 'personal') {
                $balance = $user->getPersonalBalance();
            } elseif ($balanceType === 'skins') {
                $balance = $user->getSkinsBalance();
            } else {
                throw new \Exception('Unknown balance type: ' . $balanceType);
            }

            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_TASK_V2;
            $profit->amount = $task->reward_amount;
            $profit->user_balance_id = $balance->id;
            $profit->comment = Yii::t('common', 'Выполнение задания: {task}', ['task' => $task->title], 'ru-RU');
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);
            
            $balance->recalculateBalance();
        } elseif ($task->reward_type === TaskV2::REWARD_TYPE_ITEM) {
            // Выдаем предмет
            if (!$task->reward_item_id) {
                throw new \Exception('Reward item ID not specified');
            }

            $drop = Drop::findOne($task->reward_item_id);
            if (!$drop) {
                throw new \Exception('Reward item not found');
            }

            // Количество предметов (по умолчанию 1)
            $count = 1;
            if ($task->reward_amount) {
                $count = (int)$task->reward_amount;
            }

            // Проверяем, является ли предмет валютой (ID 843 - это валюта в старой системе)
            // Проверяем, является ли предмет валютой (ID 843 - это валюта в старой системе)
            if ($drop->id == 843) {
                // 843 - это ID для денег, начисляем как валюту через Profit
                $userBalance = $user->getPersonalBalance();
                $profit = new Profit();
                $profit->status = 1;
                $profit->type = Profit::TYPE_TASK_V2;
                $profit->amount = $count;
                $profit->user_balance_id = $userBalance->id;
                $profit->comment = Yii::t('common', 'Выполнение задания: {task}', ['task' => $task->title], 'ru-RU');
                $profit->created_at = date('Y-m-d H:i:s');
                $profit->save(false);
                $userBalance->recalculateBalance();
            } else {
                // Обычный предмет - создаем запись UserDrop
                // box_id = 14 используется для заданий (как в старой системе)
                UserDrop::createRecord(
                    $user->id,
                    $drop->id,
                    14, // box_id для заданий
                    null, // sets_id
                    UserDrop::STATUS_ACTIVE,
                    false, // auto
                    $count, // count
                    null, // created_at
                    null  // parent_drop_id
                );
            }
        }
    }

    /**
     * Выдать ежедневную награду
     * @param TaskV2 $task
     * @param User $user
     * @param CheckResult|null $checkResult
     * @throws \Exception
     */
    protected function giveDailyReward(TaskV2 $task, User $user, $checkResult = null)
    {
        // Получаем список наград из check_params
        if (empty($task->check_params)) {
            throw new \Exception('Список наград не настроен');
        }

        // check_params может быть уже массивом или JSON-строкой
        if (is_array($task->check_params)) {
            $params = $task->check_params;
        } else {
            $params = json_decode($task->check_params, true);
        }
        
        if (!is_array($params) || empty($params['rewards']) || !is_array($params['rewards'])) {
            throw new \Exception('Список наград не настроен');
        }

        $rewards = $params['rewards'];
        
        // Определяем текущий индекс награды
        $currentIndex = 0;
        if ($checkResult && $checkResult->progress !== null) {
            $currentIndex = $checkResult->progress - 1; // progress начинается с 1
        } else {
            // Если progress не передан, вычисляем сами на основе TaskV2UserCompletion
            $completion = TaskV2UserCompletion::find()
                ->where(['task_id' => $task->id, 'user_id' => $user->id])
                ->one();

            $today = new \DateTime();
            $today->setTime(0, 0, 0);

            if ($completion && $completion->last_completed) {
                $lastCompletedDate = new \DateTime($completion->last_completed);
                $lastCompletedDate->setTime(0, 0, 0);
                $lastCompletedDateStr = $lastCompletedDate->format('Y-m-d');

                $yesterday = clone $today;
                $yesterday->modify('-1 day');
                $yesterdayStr = $yesterday->format('Y-m-d');

                if ($lastCompletedDateStr === $yesterdayStr) {
                    // Последовательность продолжается
                    $currentIndex = ($completion->count_completed) % count($rewards);
                } else {
                    // Пропущен день - сброс на первую
                    $currentIndex = 0;
                }
            } else {
                // Первая награда
                $currentIndex = 0;
            }
        }

        // Если дошли до последней награды - сброс на первую
        if ($currentIndex >= count($rewards)) {
            $currentIndex = 0;
        }

        $reward = $rewards[$currentIndex];
        
        // Выдаем награду
        if (isset($reward['drop_id'])) {
            // Награда - предмет
            $drop = Drop::findOne($reward['drop_id']);
            if (!$drop) {
                throw new \Exception('Предмет награды не найден');
            }

            $count = isset($reward['amount']) ? (int)$reward['amount'] : 1;

            if ($drop->id == 843) {
                // Валюта
                $userBalance = $user->getPersonalBalance();
                $profit = new Profit();
                $profit->status = 1;
                $profit->type = Profit::TYPE_TASK_V2;
                $profit->amount = $count;
                $profit->user_balance_id = $userBalance->id;
                $profit->comment = Yii::t('common', 'Задание: {task}', ['task' => $task->title], 'ru-RU');
                $profit->created_at = date('Y-m-d H:i:s');
                $profit->save(false);
                $userBalance->recalculateBalance();
            } else {
                // Обычный предмет
                UserDrop::createRecord(
                    $user->id,
                    $drop->id,
                    14, // box_id для заданий
                    null,
                    UserDrop::STATUS_ACTIVE,
                    false,
                    $count,
                    null,
                    null
                );
            }
        } elseif (isset($reward['currency']) && isset($reward['amount'])) {
            // Награда - валюта напрямую
            $balanceType = $reward['currency'] ?? 'personal';
            
            if ($balanceType === 'personal') {
                $balance = $user->getPersonalBalance();
            } elseif ($balanceType === 'skins') {
                $balance = $user->getSkinsBalance();
            } else {
                throw new \Exception('Unknown balance type: ' . $balanceType);
            }

            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_TASK_V2;
            $profit->amount = (float)$reward['amount'];
            $profit->user_balance_id = $balance->id;
            $profit->comment = Yii::t('common', 'Задание: {task}', ['task' => $task->title], 'ru-RU');
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);
            
            $balance->recalculateBalance();
        } else {
            throw new \Exception('Неверный формат награды');
        }
    }
}

