<?php

namespace common\components\tasks_v2;

use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use Yii;

/**
 * Проверка подписки на группу VK
 * TODO: Реализовать проверку через VK API
 */
class VkSubscribeGroupChecker implements TaskCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        $params = $task->check_params ?? [];
        $groupId = $params['group_id'] ?? null;

        if (!$groupId) {
            return CheckResult::failure(
                Yii::t('common', 'Настройки задания неверны: не указан ID группы VK.')
            );
        }

        // TODO: Реализовать проверку подписки через VK API
        // Пока возвращаем заглушку
        return CheckResult::failure(
            Yii::t('common', 'Проверка подписки на группу VK временно недоступна. Убедитесь, что вы подписаны на группу и попробуйте позже.')
        );
    }
}









