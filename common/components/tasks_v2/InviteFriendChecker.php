<?php

namespace common\components\tasks_v2;

use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use common\models\user\UserTree;
use Yii;

/**
 * Проверка приглашения друга (реферальная система)
 */
class InviteFriendChecker implements TaskCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        $params = $task->check_params ?? [];
        $requiredCount = $params['count'] ?? 1;

        // Получаем количество активных рефералов через UserTree
        $referralsCount = UserTree::find()
            ->where(['parent_user_id' => $user->id])
            ->andWhere(['>=', 'created_at', date('Y-m-d H:i:s', strtotime('-30 days'))])
            ->count();

        if ($referralsCount >= $requiredCount) {
            return CheckResult::success(
                Yii::t('common', 'Задание выполнено! Приглашено друзей: {count}', ['count' => $referralsCount]),
                $referralsCount,
                $requiredCount
            );
        }

        $remaining = $requiredCount - $referralsCount;
        return CheckResult::failure(
            Yii::t('common', 'Приглашено друзей: {current} из {required}. Осталось: {remaining}', [
                'current' => $referralsCount,
                'required' => $requiredCount,
                'remaining' => $remaining,
            ]),
            $referralsCount,
            $requiredCount
        );
    }
}

