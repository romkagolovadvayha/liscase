<?php

namespace api\controllers\v1;

use Yii;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use common\models\tasks_v2\TaskV2;
use common\models\tasks_v2\TaskV2UserCompletion;
use common\components\tasks_v2\TaskCheckerFactory;
use common\components\tasks_v2\CheckResult;
use api\components\jwt\JwtAuthFilter;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с заданиями (TasksV2 версия)
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="Tasks")
 */
class TasksController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // JWT авторизация требуется для всех методов
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'except' => ['options'],
        ];

        return $behaviors;
    }

    /**
     * Список заданий
     * 
     * @OA\Get(
     *     path="/v1/tasks",
     *     operationId="getTasks",
     *     tags={"Tasks"},
     *     summary="Получить список заданий",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"one_time", "repeatable"})),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"popularity", "reward", "newest"})),
     *     @OA\Response(response=200, description="Список заданий")
     * )
     */
    public function actionIndex()
    {
        $user = $this->getCurrentUser();
        $type = Yii::$app->request->get('type');
        $sort = Yii::$app->request->get('sort');

        // Кэшируем список заданий на 5 минут (без пользовательских данных)
        $cacheKey = 'api_tasks_list_' . md5(($type ?? '') . '_' . ($sort ?? ''));
        $cache = Yii::$app->cache;
        $cachedTasks = $cache->get($cacheKey);
        
        if ($cachedTasks === false) {
            // Получаем только активные задания
            $query = TaskV2::find()
                ->where(['is_active' => 1]);

            // Фильтр по типу (daily_reward всегда включен)
            if ($type && in_array($type, [TaskV2::TYPE_ONE_TIME, TaskV2::TYPE_REPEATABLE])) {
                $query->andWhere(['type' => $type]);
                // daily_reward добавляем всегда
                $query->orWhere(['type' => TaskV2::TYPE_DAILY_REWARD]);
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

            $allModels = $query->all();
            
            // Сохраняем в кэш только модели (без пользовательских данных)
            $cache->set($cacheKey, $allModels, 300); // 5 минут
        } else {
            $allModels = $cachedTasks;
        }

        // Кастомная сортировка: ежедневная награда первая, затем новые, затем выполненные
        usort($allModels, function ($a, $b) use ($user) {
            $aIsDaily = $a->type === TaskV2::TYPE_DAILY_REWARD && $a->check_type === TaskV2::CHECK_TYPE_DAILY_REWARD;
            $bIsDaily = $b->type === TaskV2::TYPE_DAILY_REWARD && $b->check_type === TaskV2::CHECK_TYPE_DAILY_REWARD;
            
            if ($aIsDaily && !$bIsDaily) return -1;
            if (!$aIsDaily && $bIsDaily) return 1;
            if ($aIsDaily && $bIsDaily) return 0;
            
            $statusA = $a->getUserStatus($user)['status'] ?? 'available';
            $statusB = $b->getUserStatus($user)['status'] ?? 'available';
            
            if ($statusA === 'completed' && $statusB !== 'completed') return 1;
            if ($statusA !== 'completed' && $statusB === 'completed') return -1;
            
            return strtotime($b->created_at) <=> strtotime($a->created_at);
        });

        // Вычисляем статистику (без daily_reward)
        $totalTasks = 0;
        $completedTasks = 0;
        $totalRewards = 0;
        $totalCoins = 0;
        $totalPotentialCoins = 0;
        $totalPotentialRewards = 0;
        
        foreach ($allModels as $task) {
            if ($task->type === TaskV2::TYPE_DAILY_REWARD && $task->check_type === TaskV2::CHECK_TYPE_DAILY_REWARD) {
                continue;
            }
            
            $totalTasks++;
            $userStatus = $task->getUserStatus($user);
            
            if ($task->reward_type === TaskV2::REWARD_TYPE_CURRENCY && $task->reward_amount) {
                $totalPotentialCoins += $task->reward_amount;
                $totalPotentialRewards++;
                if ($userStatus['status'] === 'completed') {
                    $completedTasks++;
                    $totalCoins += $task->reward_amount;
                    $totalRewards++;
                }
            } elseif ($task->reward_type === TaskV2::REWARD_TYPE_ITEM && $task->reward_item_id) {
                $totalPotentialRewards++;
                if ($userStatus['status'] === 'completed') {
                    $completedTasks++;
                    $totalRewards++;
                }
            }
        }
        
        $completionPercent = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        // Получаем прогресс для всех заданий
        $tasksProgress = [];
        foreach ($allModels as $task) {
            $isRepeatable = $task->type === TaskV2::TYPE_REPEATABLE;
            $isOneTimeWithStats = $task->type === TaskV2::TYPE_ONE_TIME && 
                                  $task->check_type === TaskV2::CHECK_TYPE_STATISTICS_PARAM;
            
            if ($isRepeatable || $isOneTimeWithStats) {
                try {
                    $checker = TaskCheckerFactory::create($task);
                    $checkResult = $checker->check($task, $user);
                    $tasksProgress[$task->id] = [
                        'progress' => $checkResult->progress,
                        'maxProgress' => $task->max_progress ?? $checkResult->maxProgress,
                    ];
                } catch (\Exception $e) {
                    Yii::error('Failed to get progress for task ' . $task->id . ': ' . $e->getMessage());
                }
            }
        }

        // Форматируем задания для API
        $tasks = [];
        foreach ($allModels as $task) {
            $userStatus = $task->getUserStatus($user);
            $tasks[] = $this->formatTask($task, $user, $tasksProgress[$task->id] ?? null);
        }

        // Пагинация
        $page = (int)Yii::$app->request->get('page', 1);
        $pageSize = (int)Yii::$app->request->get('pageSize', 20);
        $offset = ($page - 1) * $pageSize;
        $totalCount = count($tasks);
        $tasks = array_slice($tasks, $offset, $pageSize);

        return $this->successResponse([
            'tasks' => $tasks,
            'statistics' => [
                'totalTasks' => $totalTasks,
                'completedTasks' => $completedTasks,
                'completionPercent' => $completionPercent,
                'totalCoins' => $totalCoins,
                'totalRewards' => $totalRewards,
                'totalPotentialCoins' => $totalPotentialCoins,
                'totalPotentialRewards' => $totalPotentialRewards,
            ],
            'tasksProgress' => $tasksProgress,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'totalCount' => $totalCount,
                'totalPages' => (int)ceil($totalCount / $pageSize),
            ],
        ]);
    }

    /**
     * Детальная информация о задании
     * 
     * @OA\Get(
     *     path="/v1/tasks/{id}",
     *     operationId="getTaskDetail",
     *     tags={"Tasks"},
     *     summary="Получить детальную информацию о задании",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID задания",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Детальная информация о задании",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=404, description="Задание не найдено")
     * )
     */
    public function actionDetail($id)
    {
        $user = $this->getCurrentUser();

        $task = TaskV2::find()
            ->where(['id' => $id, 'is_active' => 1])
            ->one();

        if (!$task) {
            throw new NotFoundHttpException('Задание не найдено');
        }

        $userStatus = $task->getUserStatus($user);

        // Для ежедневных наград
        if ($task->type === TaskV2::TYPE_DAILY_REWARD && $task->check_type === TaskV2::CHECK_TYPE_DAILY_REWARD) {
            $userStatus['currentReward'] = $task->getCurrentDailyReward($user);
            $dailyRewardList = $task->getDailyRewardList($user);
        } else {
            $dailyRewardList = null;
        }

        // Прогресс
        $progress = null;
        $maxProgress = $task->max_progress;
        
        try {
            $checker = TaskCheckerFactory::create($task);
            $checkResult = $checker->check($task, $user);
            $progress = $checkResult->progress;
            if ($maxProgress === null) {
                $maxProgress = $checkResult->maxProgress;
            }
        } catch (\Exception $e) {
            Yii::error('Failed to check task: ' . $e->getMessage());
        }

        // VK данные
        $vkCode = null;
        $vkGroupId = null;
        if ($task->check_type === TaskV2::CHECK_TYPE_VK_SUBSCRIBE_GROUP) {
            $params = $task->check_params ?? [];
            if (is_string($params)) {
                $params = json_decode($params, true);
            }
            $vkGroupId = $params['group_id'] ?? null;
            if ($vkGroupId) {
                $vkCode = \common\models\user\UserConfirmCode::createTypeVkGroup($user->id);
            }
        }

        return $this->successResponse([
            'task' => $this->formatTaskDetail($task),
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
     * 
     * @OA\Post(
     *     path="/v1/tasks/{id}/check",
     *     operationId="checkTask",
     *     tags={"Tasks"},
     *     summary="Проверить и выполнить задание",
     *     description="Требует JWT авторизации. Проверяет выполнение задания и начисляет награду.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID задания",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Задание проверено/выполнено",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Задание уже выполнено или не выполнено"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=404, description="Задание не найдено")
     * )
     */
    public function actionCheck($id)
    {
        $user = $this->getCurrentUser();

        $task = TaskV2::findOne(['id' => $id, 'is_active' => 1]);
        if (!$task) {
            return $this->errorResponse('TASK_NOT_FOUND', 'Задание не найдено', [], 404);
        }

        $userStatus = $task->getUserStatus($user);
        
        if (in_array($userStatus['status'], ['completed', 'limit_reached', 'unavailable'])) {
            return $this->errorResponse('TASK_UNAVAILABLE', $userStatus['message'], [], 400);
        }

        if ($task->global_limit !== null && $task->global_completed >= $task->global_limit) {
            return $this->errorResponse('GLOBAL_LIMIT_REACHED', 'Задание выполнено всеми участниками', [], 400);
        }

        try {
            $checker = TaskCheckerFactory::create($task);
            $checkResult = $checker->check($task, $user);
        } catch (\Exception $e) {
            Yii::error('Failed to check task: ' . $e->getMessage());
            return $this->errorResponse('CHECK_ERROR', 'Ошибка при проверке задания', [], 500);
        }

        if (!$checkResult->success) {
            $maxProgress = $task->max_progress ?? $checkResult->maxProgress;
            $response = [
                'success' => false,
                'message' => $checkResult->message,
                'progress' => $checkResult->progress,
                'maxProgress' => $maxProgress,
            ];
            
            if (!empty($checkResult->redirectUrl)) {
                $response['redirect'] = $checkResult->redirectUrl;
            }
            
            return $this->successResponse($response);
        }

        // Выдаем награду
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $completion = TaskV2UserCompletion::createOrUpdate($task->id, $user->id);
            $task->global_completed++;
            $task->save(false);

            $this->giveReward($task, $user, $checkResult);

            $transaction->commit();

            $maxProgress = $task->max_progress ?? $checkResult->maxProgress;
            return $this->successResponse([
                'success' => true,
                'message' => 'Задание выполнено! Награда выдана.',
                'progress' => $checkResult->progress,
                'maxProgress' => $maxProgress,
            ]);
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error('Failed to complete task: ' . $e->getMessage());
            return $this->errorResponse('REWARD_ERROR', 'Ошибка при выдаче награды', [], 500);
        }
    }

    /**
     * Форматирование задания для списка
     */
    protected function formatTask($task, $user, $progressData = null)
    {
        $userStatus = $task->getUserStatus($user);
        
        // Для ежедневных наград добавляем currentReward
        if ($task->type === TaskV2::TYPE_DAILY_REWARD && $task->check_type === TaskV2::CHECK_TYPE_DAILY_REWARD) {
            $userStatus['currentReward'] = $task->getCurrentDailyReward($user);
        }
        
        return [
            'id' => $task->id,
            'title' => $task->title,
            'short_description' => $task->short_description,
            'type' => $task->type,
            'check_type' => $task->check_type,
            'reward_type' => $task->reward_type,
            'reward_amount' => $task->reward_amount ? (float)$task->reward_amount : null,
            'reward_item' => $task->rewardItem ? [
                'id' => $task->rewardItem->id,
                'name' => $task->rewardItem->name,
                'image' => $task->rewardItem->imageOrig ? $task->rewardItem->imageOrig->getImagePubUrl() : null,
            ] : null,
            'is_vip_only' => (bool)$task->is_vip_only,
            'image' => $task->image_path ? rtrim(Yii::$app->settings->get('s3_publicUrl'), '/') . '/' . ltrim($task->image_path, '/') : null,
            'global_completed' => (int)$task->global_completed,
            'sort' => $task->sort,
            'created_at' => $task->created_at,
            'userStatus' => $userStatus,
            'progress' => $progressData['progress'] ?? null,
            'maxProgress' => $progressData['maxProgress'] ?? null,
        ];
    }

    /**
     * Форматирование задания для детального просмотра
     */
    protected function formatTaskDetail($task)
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'short_description' => $task->short_description,
            'full_description' => $task->full_description,
            'image' => $task->image_path ? rtrim(Yii::$app->settings->get('s3_publicUrl'), '/') . '/' . ltrim($task->image_path, '/') : null,
            'type' => $task->type,
            'check_type' => $task->check_type,
            'reward_type' => $task->reward_type,
            'reward_amount' => $task->reward_amount ? (float)$task->reward_amount : null,
            'reward_item' => $task->rewardItem ? [
                'id' => $task->rewardItem->id,
                'name' => $task->rewardItem->name,
                'image' => $task->rewardItem->imageOrig ? $task->rewardItem->imageOrig->getImagePubUrl() : null,
            ] : null,
            'is_vip_only' => (bool)$task->is_vip_only,
            'button_text' => $task->button_text,
            'extra_buttons' => is_array($task->extra_buttons) ? $task->extra_buttons : ($task->extra_buttons ? json_decode($task->extra_buttons, true) : null),
            'max_progress' => $task->max_progress,
            'global_completed' => (int)$task->global_completed,
        ];
    }

    /**
     * Выдача награды (копируем из TasksV2Controller)
     */
    protected function giveReward($task, $user, $checkResult = null)
    {
        if ($task->type === TaskV2::TYPE_DAILY_REWARD && $task->check_type === TaskV2::CHECK_TYPE_DAILY_REWARD) {
            $this->giveDailyReward($task, $user, $checkResult);
            return;
        }

        if ($task->reward_type === TaskV2::REWARD_TYPE_CURRENCY) {
            $balanceType = $task->reward_currency ?? 'personal';
            $balance = $balanceType === 'personal' ? $user->getPersonalBalance() : $user->getSkinsBalance();

            $profit = new \common\models\profit\Profit();
            $profit->status = 1;
            $profit->type = \common\models\profit\Profit::TYPE_TASK_V2;
            $profit->amount = $task->reward_amount;
            $profit->user_balance_id = $balance->id;
            $profit->comment = 'Выполнение задания: ' . $task->title;
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);
            
            $balance->recalculateBalance();
        } elseif ($task->reward_type === TaskV2::REWARD_TYPE_ITEM && $task->reward_item_id) {
            $drop = \common\models\box\Drop::findOne($task->reward_item_id);
            if ($drop) {
                $count = (int)($task->reward_amount ?: 1);
                
                if ($drop->id == 843) {
                    // Валюта через Profit
                    $userBalance = $user->getPersonalBalance();
                    $profit = new \common\models\profit\Profit();
                    $profit->status = 1;
                    $profit->type = \common\models\profit\Profit::TYPE_TASK_V2;
                    $profit->amount = $count;
                    $profit->user_balance_id = $userBalance->id;
                    $profit->comment = 'Выполнение задания: ' . $task->title;
                    $profit->created_at = date('Y-m-d H:i:s');
                    $profit->save(false);
                    $userBalance->recalculateBalance();
                } else {
                    \common\models\user\UserDrop::createRecord(
                        $user->id,
                        $drop->id,
                        14,
                        null,
                        \common\models\user\UserDrop::STATUS_ACTIVE,
                        false,
                        $count,
                        null,
                        null
                    );
                }
            }
        }
    }

    /**
     * Выдача ежедневной награды (упрощенная версия)
     */
    protected function giveDailyReward($task, $user, $checkResult = null)
    {
        // Упрощенная версия - полная логика аналогична TasksV2Controller::giveDailyReward
        $params = is_array($task->check_params) ? $task->check_params : json_decode($task->check_params, true);
        if (empty($params['rewards'])) {
            throw new \Exception('Список наград не настроен');
        }

        $rewards = $params['rewards'];
        $completion = TaskV2UserCompletion::find()
            ->where(['task_id' => $task->id, 'user_id' => $user->id])
            ->one();

        $currentIndex = 0;
        if ($completion) {
            $currentIndex = ($completion->count_completed) % count($rewards);
        }

        $reward = $rewards[$currentIndex] ?? $rewards[0];
        
        if (isset($reward['drop_id'])) {
            $drop = \common\models\box\Drop::findOne($reward['drop_id']);
            if ($drop) {
                $count = (int)($reward['amount'] ?? 1);
                if ($drop->id == 843) {
                    $userBalance = $user->getPersonalBalance();
                    $profit = new \common\models\profit\Profit();
                    $profit->status = 1;
                    $profit->type = \common\models\profit\Profit::TYPE_TASK_V2;
                    $profit->amount = $count;
                    $profit->user_balance_id = $userBalance->id;
                    $profit->comment = 'Задание: ' . $task->title;
                    $profit->created_at = date('Y-m-d H:i:s');
                    $profit->save(false);
                    $userBalance->recalculateBalance();
                } else {
                    \common\models\user\UserDrop::createRecord(
                        $user->id,
                        $drop->id,
                        14,
                        null,
                        \common\models\user\UserDrop::STATUS_ACTIVE,
                        false,
                        $count,
                        null,
                        null
                    );
                }
            }
        } elseif (isset($reward['currency']) && isset($reward['amount'])) {
            $balanceType = $reward['currency'] ?? 'personal';
            $balance = $balanceType === 'personal' ? $user->getPersonalBalance() : $user->getSkinsBalance();
            
            $profit = new \common\models\profit\Profit();
            $profit->status = 1;
            $profit->type = \common\models\profit\Profit::TYPE_TASK_V2;
            $profit->amount = (float)$reward['amount'];
            $profit->user_balance_id = $balance->id;
            $profit->comment = 'Задание: ' . $task->title;
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);
            $balance->recalculateBalance();
        }
    }
}

