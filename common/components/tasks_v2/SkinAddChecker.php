<?php

namespace common\components\tasks_v2;

use common\models\serverskin\ServerSkin;
use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use Yii;
use yii\helpers\Json;

/**
 * Проверка добавления скинов (одобренных модерацией)
 */
class SkinAddChecker implements TaskCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        $params = is_array($task->check_params) ? $task->check_params : Json::decode($task->check_params, true);
        $params = $params ?: [];

        $requiredCount = (int)($params['count'] ?? $params['required_count'] ?? 1);

        if ($requiredCount <= 0) {
            return CheckResult::failure(
                Yii::t('common', 'Настройки задания неверны: не указано требуемое количество скинов.')
            );
        }

        $currentCount = ServerSkin::find()
            ->where(['user_id' => $user->id])
            ->andWhere(['status' => ServerSkin::STATUS_ACTIVE])
            ->count();

        if ($currentCount >= $requiredCount) {
            return CheckResult::success(
                Yii::t('common', 'Задание выполнено! Одобренных скинов: {current} из {required}', [
                    'current' => $currentCount,
                    'required' => $requiredCount,
                ]),
                $currentCount,
                $requiredCount
            );
        }

        $remaining = $requiredCount - $currentCount;
        return CheckResult::failure(
            Yii::t('common', 'Одобренных скинов: {current} из {required}. Осталось: {remaining}', [
                'current' => $currentCount,
                'required' => $requiredCount,
                'remaining' => $remaining,
            ]),
            $currentCount,
            $requiredCount
        );
    }
}
