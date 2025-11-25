<?php

namespace common\components\tasks_v2;

use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use Yii;

/**
 * Проверка вступления в Discord
 * TODO: Реализовать проверку через Discord API
 */
class DiscordJoinChecker implements TaskCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        // TODO: Реализовать проверку вступления в Discord через Discord API
        // Пока возвращаем заглушку
        return CheckResult::failure(
            Yii::t('common', 'Проверка вступления в Discord временно недоступна. Убедитесь, что вы вступили в сервер и попробуйте позже.')
        );
    }
}









