<?php

namespace common\components\tasks_v2;

use common\models\statistics\Statistics;
use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use Yii;

/**
 * Проверка количества убийств ботов
 */
class KillBotsCountChecker implements TaskCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        $params = $task->check_params ?? [];
        $requiredCount = $params['count'] ?? 0;

        if ($requiredCount <= 0) {
            return CheckResult::failure(
                Yii::t('common', 'Настройки задания неверны: не указано требуемое количество.')
            );
        }

        // Получаем статистику убийств ботов
        $statistics = Statistics::find()
            ->where(['user_id' => $user->id])
            ->one();

        $currentCount = 0;
        if ($statistics) {
            // Предполагаем, что в Statistics есть поле bots_killed или аналогичное
            // Если нет, нужно будет добавить или использовать другой источник
            $currentCount = (int)($statistics->bots_killed ?? 0);
        }

        if ($currentCount >= $requiredCount) {
            return CheckResult::success(
                Yii::t('common', 'Задание выполнено! Убито ботов: {count}', ['count' => $currentCount]),
                $currentCount,
                $requiredCount
            );
        }

        $remaining = $requiredCount - $currentCount;
        return CheckResult::failure(
            Yii::t('common', 'Убито ботов: {current} из {required}. Осталось: {remaining}', [
                'current' => $currentCount,
                'required' => $requiredCount,
                'remaining' => $remaining,
            ]),
            $currentCount,
            $requiredCount
        );
    }
}









