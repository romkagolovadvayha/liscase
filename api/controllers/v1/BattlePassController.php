<?php

namespace api\controllers\v1;

use common\components\tasks_v2\StatisticsParamChecker;
use common\components\tasks_v2\TaskCheckerFactory;
use common\models\battle_pass\BattlePassSeason;
use common\models\battle_pass\BattlePassUserSeason;
use common\models\battle_pass\BattlePassUserTask;
use common\models\box\Drop;
use common\models\box\DropDrop;
use common\models\medals\UserMedal;
use common\models\tasks_v2\TaskV2;
use common\models\tasks_v2\TaskV2UserCompletion;
use common\models\user\User;
use common\models\user\UserDrop;
use common\models\user\UserVip;
use Yii;

/**
 * Public API for the active seasonal Battle Pass.
 */
class BattlePassController extends TasksController
{
    /**
     * The season overview is public, while valid JWTs still hydrate the
     * response with the current user's progress. Mutating/detail actions keep
     * enforcing authentication through getCurrentUser().
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['throwException'] = false;

        return $behaviors;
    }

    /**
     * Battle Pass item rewards use the same package sizes as the store.
     * Sets are unpacked into their configured contents instead of creating an
     * unusable UserDrop for the parent set.
     */
    protected function giveReward($task, $user, $checkResult = null)
    {
        if ($task->reward_type !== TaskV2::REWARD_TYPE_ITEM || !$task->reward_item_id) {
            parent::giveReward($task, $user, $checkResult);
            return;
        }

        $drop = Drop::findOneCachedWithImageOrig((int)$task->reward_item_id);
        if (!$drop || (int)$drop->id === 843) {
            parent::giveReward($task, $user, $checkResult);
            return;
        }

        $quantity = max(1, (int)($task->reward_amount ?: 1));
        if ((int)$drop->drop_type === Drop::TYPE_SET) {
            $subDrops = DropDrop::find()
                ->where(['parent_drop_id' => (int)$drop->id])
                ->all();
            if (!$subDrops) {
                throw new \RuntimeException('Набор награды Battle Pass не содержит предметов');
            }

            foreach ($subDrops as $subDrop) {
                UserDrop::createRecord(
                    (int)$user->id,
                    (int)$subDrop->drop_id,
                    14,
                    null,
                    UserDrop::STATUS_ACTIVE,
                    false,
                    max(1, (int)$subDrop->count) * $quantity,
                    null,
                    (int)$drop->id
                );
            }
            return;
        }

        UserDrop::createRecord(
            (int)$user->id,
            (int)$drop->id,
            14,
            null,
            UserDrop::STATUS_ACTIVE,
            false,
            max(1, (int)$drop->count) * $quantity
        );
    }

    public function actionIndex()
    {
        $season = BattlePassSeason::findActive();
        if (!$season) {
            return $this->errorResponse('BATTLE_PASS_NOT_FOUND', 'Активный сезон Battle Pass не найден', [], 404);
        }

        $user = Yii::$app->user->identity;
        $payload = $user instanceof User
            ? $this->buildPayload($season, $user)
            : $this->buildPublicPayload($season);

        return $this->successResponse($payload);
    }

    public function actionDetail($id)
    {
        $user = $this->getCurrentUser();
        $season = BattlePassSeason::findActive();
        if (!$season) {
            return $this->errorResponse('BATTLE_PASS_NOT_FOUND', 'Активный сезон Battle Pass не найден', [], 404);
        }

        $payload = $this->buildPayload($season, $user);
        foreach (array_merge($payload['freeTasks'], $payload['vipTasks']) as $task) {
            if ((int)$task['id'] !== (int)$id) {
                continue;
            }
            $model = TaskV2::findOne((int)$id);
            $task['full_description'] = $model && $model->full_description
                ? Yii::t('database', $model->full_description)
                : null;
            $task['button_text'] = $model && $model->button_text
                ? Yii::t('database', $model->button_text)
                : 'Проверить прогресс';
            return $this->successResponse([
                'task' => $task,
                'userStatus' => $task['userStatus'],
                'progress' => $task['progress'],
                'maxProgress' => $task['maxProgress'],
            ]);
        }

        return $this->errorResponse('TASK_NOT_FOUND', 'Задание Battle Pass не найдено', [], 404);
    }

    public function actionCheck($id)
    {
        $user = $this->getCurrentUser();
        $season = BattlePassSeason::findActive();
        if (!$season) {
            return $this->errorResponse('BATTLE_PASS_NOT_FOUND', 'Активный сезон Battle Pass не найден', [], 404);
        }

        $tasks = $this->loadSeasonTasks($season);
        $task = null;
        foreach ($tasks as $candidate) {
            if ((int)$candidate->id === (int)$id) {
                $task = $candidate;
                break;
            }
        }
        if (!$task) {
            return $this->errorResponse('TASK_NOT_FOUND', 'Задание Battle Pass не найдено', [], 404);
        }

        $completions = $this->loadTaskV2CompletionsIndexed($user, $tasks);
        if ($this->isCompleted($task, $completions)) {
            return $this->errorResponse('TASK_ALREADY_COMPLETED', 'Задание уже выполнено', [], 400);
        }

        $hasVip = (bool)UserVip::getActiveVip((int)$user->id);
        $currentTask = $this->findCurrentTask($tasks, $completions, $hasVip);
        if (!$currentTask || (int)$currentTask->id !== (int)$task->id) {
            return $this->errorResponse(
                'TASK_LOCKED',
                $task->is_vip_only && !$hasVip
                    ? 'Для дополнительного задания требуется VIP'
                    : 'Сначала выполните предыдущее задание',
                [],
                400
            );
        }

        try {
            $state = $this->ensureTaskUnlocked($season, $task, $user);
            $checkResult = $this->checkTask($season, $task, $user, $state);
        } catch (\Throwable $e) {
            Yii::error('Battle Pass check failed: ' . $e->getMessage(), __METHOD__);
            return $this->errorResponse('CHECK_ERROR', 'Ошибка при проверке задания', [], 500);
        }

        if (!$checkResult->success) {
            return $this->successResponse([
                'success' => false,
                'message' => $checkResult->message,
                'progress' => $checkResult->progress,
                'maxProgress' => $task->max_progress ?? $checkResult->maxProgress,
            ]);
        }

        $nextTask = $this->findNextTask($tasks, $task);
        $nextBaseline = null;
        if ($nextTask && (!$nextTask->is_vip_only || $hasVip)) {
            $nextBaseline = $this->getTaskSnapshot($season, $nextTask, $user);
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // У текущего этапа персональная запись уже существует после ensureTaskUnlocked().
            // Блокируем её первой, чтобы два параллельных check одного пользователя
            // выполнялись строго последовательно и не создавали gap-lock deadlock при
            // одновременной вставке task_v2_user_completion.
            $lockedTaskState = Yii::$app->db->createCommand(
                'SELECT id FROM battle_pass_user_task WHERE season_id = :seasonId AND task_id = :taskId AND user_id = :userId FOR UPDATE',
                [
                    ':seasonId' => (int)$season->id,
                    ':taskId' => (int)$task->id,
                    ':userId' => (int)$user->id,
                ]
            )->queryScalar();
            if (!$lockedTaskState) {
                throw new \RuntimeException('Не найдена запись открытого задания Battle Pass');
            }

            // После персональной блокировки повторный запрос увидит уже зафиксированное
            // выполнение и завершится до выдачи награды.
            $lockedCompletion = Yii::$app->db->createCommand(
                'SELECT id, count_completed FROM task_v2_user_completion WHERE task_id = :taskId AND user_id = :userId FOR UPDATE',
                [':taskId' => (int)$task->id, ':userId' => (int)$user->id]
            )->queryOne();
            if ($lockedCompletion && (int)$lockedCompletion['count_completed'] > 0) {
                $transaction->rollBack();
                return $this->errorResponse('TASK_ALREADY_COMPLETED', 'Задание уже выполнено', [], 400);
            }

            $now = date('Y-m-d H:i:s');
            if ($lockedCompletion) {
                $updated = TaskV2UserCompletion::updateAll([
                    'count_completed' => 1,
                    'last_completed' => $now,
                    'updated_at' => $now,
                ], [
                    'id' => (int)$lockedCompletion['id'],
                    'count_completed' => 0,
                ]);
                if ($updated !== 1) {
                    throw new \RuntimeException('Не удалось зафиксировать выполнение задания Battle Pass');
                }
            } else {
                $completion = new TaskV2UserCompletion();
                $completion->task_id = (int)$task->id;
                $completion->user_id = (int)$user->id;
                $completion->count_completed = 1;
                if (!$completion->save(false)) {
                    throw new \RuntimeException('Не удалось создать выполнение задания Battle Pass');
                }
            }
            TaskV2::updateAllCounters(['global_completed' => 1], ['id' => (int)$task->id]);
            $this->giveReward($task, $user, $checkResult);

            $seasonCompleted = false;
            if (!$task->is_vip_only && $this->isLastFreeTask($tasks, $task)) {
                $seasonCompleted = $this->completeSeason($season, $user);
            }

            if ($nextTask && $nextBaseline !== null) {
                BattlePassUserTask::unlock(
                    (int)$season->id,
                    (int)$nextTask->id,
                    (int)$user->id,
                    (int)$nextBaseline
                );
            }

            $transaction->commit();
            $this->bumpTaskV2UserCompletionCacheVersion((int)$user->id);
            Yii::$app->cache->delete('homepage_medals_' . (int)$user->id);

            return $this->successResponse([
                'success' => true,
                'message' => $seasonCompleted
                    ? 'Battle Pass пройден! Финальная награда и медаль начислены.'
                    : 'Задание выполнено! Награда выдана.',
                'progress' => $checkResult->progress,
                'maxProgress' => $task->max_progress ?? $checkResult->maxProgress,
                'seasonCompleted' => $seasonCompleted,
            ]);
        } catch (\yii\db\IntegrityException $e) {
            $transaction->rollBack();
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                return $this->errorResponse('TASK_ALREADY_COMPLETED', 'Задание уже выполнено', [], 400);
            }
            Yii::error('Battle Pass reward integrity error: ' . $e->getMessage(), __METHOD__);
            return $this->errorResponse('REWARD_ERROR', 'Ошибка при выдаче награды', [], 500);
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error('Battle Pass reward failed: ' . $e->getMessage(), __METHOD__);
            return $this->errorResponse('REWARD_ERROR', 'Ошибка при выдаче награды', [], 500);
        }
    }

    private function buildPayload(BattlePassSeason $season, User $user): array
    {
        $tasks = $this->loadSeasonTasks($season);
        $completions = $this->loadTaskV2CompletionsIndexed($user, $tasks);
        $hasVip = (bool)UserVip::getActiveVip((int)$user->id);
        $currentTask = $this->findCurrentTask($tasks, $completions, $hasVip);
        if ($currentTask) {
            $this->ensureTaskUnlocked($season, $currentTask, $user);
        }

        $states = BattlePassUserTask::find()
            ->where(['season_id' => (int)$season->id, 'user_id' => (int)$user->id])
            ->indexBy('task_id')
            ->all();

        $freeTasks = [];
        $vipTasks = [];
        $totalFree = 0;
        $completedFree = 0;
        foreach ($tasks as $task) {
            $completed = $this->isCompleted($task, $completions);
            if (!$task->is_vip_only) {
                $totalFree++;
                if ($completed) {
                    $completedFree++;
                }
            }

            $isCurrent = $currentTask && (int)$currentTask->id === (int)$task->id;
            $status = $this->buildTaskStatus($task, $completed, $isCurrent, $hasVip);
            $progress = $completed ? (int)($task->max_progress ?: 1) : 0;
            $maxProgress = (int)($task->max_progress ?: 1);
            if ($isCurrent && isset($states[(int)$task->id])) {
                $result = $this->checkTask($season, $task, $user, $states[(int)$task->id]);
                $progress = min($maxProgress, max(0, (int)$result->progress));
                $maxProgress = (int)($task->max_progress ?? $result->maxProgress ?? 1);
            }

            $formatted = $this->formatBattlePassTask($task, $status, $progress, $maxProgress);
            if ($task->is_vip_only) {
                $vipTasks[] = $formatted;
            } else {
                $freeTasks[] = $formatted;
            }
        }

        $seasonCompletion = BattlePassUserSeason::findOne([
            'season_id' => (int)$season->id,
            'user_id' => (int)$user->id,
        ]);

        return [
            'isAuthenticated' => true,
            'season' => $this->formatSeason($season),
            'progress' => [
                'completed' => $completedFree,
                'total' => $totalFree,
                'percent' => $totalFree > 0 ? (int)round(($completedFree / $totalFree) * 100) : 0,
                'isCompleted' => $seasonCompletion !== null || ($totalFree > 0 && $completedFree === $totalFree),
                'currentTaskId' => $currentTask ? (int)$currentTask->id : null,
            ],
            'hasVip' => $hasVip,
            'freeTasks' => $freeTasks,
            'vipTasks' => $vipTasks,
        ];
    }

    private function buildPublicPayload(BattlePassSeason $season): array
    {
        $freeTasks = [];
        $vipTasks = [];

        foreach ($this->loadSeasonTasks($season) as $task) {
            $formatted = $this->formatBattlePassTask(
                $task,
                [
                    'status' => 'locked',
                    'message' => 'Войдите, чтобы выполнить задание',
                ],
                0,
                max(1, (int)($task->max_progress ?: 1))
            );

            if ($task->is_vip_only) {
                $vipTasks[] = $formatted;
            } else {
                $freeTasks[] = $formatted;
            }
        }

        return [
            'isAuthenticated' => false,
            'season' => $this->formatSeason($season),
            'progress' => [
                'completed' => 0,
                'total' => count($freeTasks),
                'percent' => 0,
                'isCompleted' => false,
                'currentTaskId' => null,
            ],
            'hasVip' => false,
            'freeTasks' => $freeTasks,
            'vipTasks' => $vipTasks,
        ];
    }

    /** @return TaskV2[] */
    private function loadSeasonTasks(BattlePassSeason $season): array
    {
        return TaskV2::find()
            ->where([
                'type' => TaskV2::TYPE_BATTLE_PASS,
                'battle_pass_season_id' => (int)$season->id,
                'is_active' => 1,
            ])
            ->orderBy(['battle_pass_position' => SORT_ASC])
            ->all();
    }

    private function findCurrentTask(array $tasks, array $completions, bool $hasVip): ?TaskV2
    {
        foreach ($tasks as $task) {
            if ($task->is_vip_only) {
                continue;
            }
            if (!$this->isCompleted($task, $completions)) {
                return $task;
            }
        }
        if (!$hasVip) {
            return null;
        }
        foreach ($tasks as $task) {
            if ($task->is_vip_only && !$this->isCompleted($task, $completions)) {
                return $task;
            }
        }
        return null;
    }

    private function findNextTask(array $tasks, TaskV2 $current): ?TaskV2
    {
        foreach ($tasks as $task) {
            if ((int)$task->battle_pass_position > (int)$current->battle_pass_position) {
                return $task;
            }
        }
        return null;
    }

    private function isLastFreeTask(array $tasks, TaskV2 $current): bool
    {
        $lastFree = null;
        foreach ($tasks as $task) {
            if (!$task->is_vip_only) {
                $lastFree = $task;
            }
        }
        return $lastFree && (int)$lastFree->id === (int)$current->id;
    }

    private function isCompleted(TaskV2 $task, array $completions): bool
    {
        $completion = $completions[(int)$task->id] ?? null;
        return $completion && (int)$completion->count_completed > 0;
    }

    private function ensureTaskUnlocked(BattlePassSeason $season, TaskV2 $task, User $user): BattlePassUserTask
    {
        $existing = BattlePassUserTask::findOne(['user_id' => (int)$user->id, 'task_id' => (int)$task->id]);
        if ($existing) {
            return $existing;
        }
        return BattlePassUserTask::unlock(
            (int)$season->id,
            (int)$task->id,
            (int)$user->id,
            $this->getTaskSnapshot($season, $task, $user)
        );
    }

    private function getTaskSnapshot(BattlePassSeason $season, TaskV2 $task, User $user): int
    {
        if ($task->check_type !== TaskV2::CHECK_TYPE_STATISTICS_PARAM) {
            return 0;
        }
        $checker = new StatisticsParamChecker();
        return $checker->getCurrentValue($task, $user, date('Y-m-d', strtotime($season->starts_at)));
    }

    private function checkTask(BattlePassSeason $season, TaskV2 $task, User $user, BattlePassUserTask $state)
    {
        if ($task->check_type === TaskV2::CHECK_TYPE_STATISTICS_PARAM) {
            $checker = new StatisticsParamChecker();
            return $checker->checkFromBaseline(
                $task,
                $user,
                (int)$state->baseline_value,
                date('Y-m-d', strtotime($season->starts_at))
            );
        }
        return TaskCheckerFactory::create($task)->check($task, $user);
    }

    private function completeSeason(BattlePassSeason $season, User $user): bool
    {
        $existing = BattlePassUserSeason::findOne(['season_id' => (int)$season->id, 'user_id' => (int)$user->id]);
        if ($existing) {
            return false;
        }

        $completion = new BattlePassUserSeason();
        $completion->season_id = (int)$season->id;
        $completion->user_id = (int)$user->id;
        $completion->completed_at = date('Y-m-d H:i:s');

        $rewardTask = new TaskV2();
        $rewardTask->type = TaskV2::TYPE_BATTLE_PASS;
        $rewardTask->title = $season->name . ': финальная награда';
        $rewardTask->reward_type = $season->reward_type;
        $rewardTask->reward_item_id = $season->reward_item_id;
        $rewardTask->reward_currency = $season->reward_currency;
        $rewardTask->reward_amount = $season->reward_amount;
        $this->giveReward($rewardTask, $user);
        $completion->reward_given_at = date('Y-m-d H:i:s');
        if (!$completion->save()) {
            throw new \RuntimeException('Не удалось завершить сезон');
        }

        if ($season->medal_id) {
            UserMedal::award(
                (int)$user->id,
                (int)$season->medal_id,
                UserMedal::SOURCE_BATTLE_PASS,
                (int)$season->id,
                'Автоматически за прохождение ' . $season->name
            );
        }
        return true;
    }

    private function buildTaskStatus(TaskV2 $task, bool $completed, bool $isCurrent, bool $hasVip): array
    {
        if ($completed) {
            return ['status' => 'completed', 'message' => 'Выполнено'];
        }
        if ($task->is_vip_only && !$hasVip) {
            return ['status' => 'unavailable', 'message' => 'Требуется VIP'];
        }
        if ($isCurrent) {
            return ['status' => 'available', 'message' => 'Доступно'];
        }
        return ['status' => 'locked', 'message' => 'Сначала выполните предыдущее задание'];
    }

    private function formatBattlePassTask(TaskV2 $task, array $status, int $progress, int $maxProgress): array
    {
        $reward = $task->getRewardDropCached();
        return [
            'id' => (int)$task->id,
            'position' => (int)$task->battle_pass_position,
            'title' => Yii::t('database', $task->title),
            'short_description' => $task->short_description ? Yii::t('database', $task->short_description) : null,
            'type' => $task->type,
            'check_type' => $task->check_type,
            'reward_type' => $task->reward_type,
            'reward_amount' => $task->reward_amount ? (float)$task->reward_amount : null,
            'reward_item' => $reward ? [
                'id' => (int)$reward->id,
                'name' => Yii::t('database', $reward->name),
                'image' => $reward->imageOrig ? $reward->imageOrig->getImagePubUrl() : null,
                'count' => max(1, (int)$reward->count),
                'drop_type' => (int)$reward->drop_type,
            ] : null,
            'is_vip_only' => (bool)$task->is_vip_only,
            'global_completed' => (int)$task->global_completed,
            'sort' => (int)$task->battle_pass_position,
            'created_at' => $task->created_at,
            'userStatus' => $status,
            'progress' => $progress,
            'maxProgress' => $maxProgress,
        ];
    }

    private function formatSeason(BattlePassSeason $season): array
    {
        $reward = $season->getRewardDropCached();
        $medal = $season->medal;
        return [
            'id' => (int)$season->id,
            'name' => Yii::t('database', $season->name),
            'seasonNumber' => (int)$season->season_number,
            'description' => $season->description ? Yii::t('database', $season->description) : null,
            'startsAt' => $season->starts_at,
            'endsAt' => $season->ends_at,
            'finalReward' => [
                'type' => $season->reward_type,
                'amount' => $season->reward_amount ? (float)$season->reward_amount : null,
                'item' => $reward ? [
                    'id' => (int)$reward->id,
                    'name' => Yii::t('database', $reward->name),
                    'image' => $reward->imageOrig ? $reward->imageOrig->getImagePubUrl() : null,
                    'count' => max(1, (int)$reward->count),
                    'drop_type' => (int)$reward->drop_type,
                ] : null,
            ],
            'medal' => $medal ? [
                'id' => (int)$medal->id,
                'name' => Yii::t('database', $medal->name),
                'description' => $medal->description ? Yii::t('database', $medal->description) : null,
                'image' => $medal->getImageUrl(),
            ] : null,
        ];
    }
}
