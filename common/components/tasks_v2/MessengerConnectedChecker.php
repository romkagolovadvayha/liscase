<?php

namespace common\components\tasks_v2;

use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use Yii;

/**
 * Проверяет подключение хотя бы одного бота: Telegram или ВКонтакте.
 */
class MessengerConnectedChecker implements TaskCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        $telegramConnected = !empty($user->telegram_chat_id);
        $vkConnected = !empty($user->vk_id);

        if ($telegramConnected || $vkConnected) {
            return CheckResult::success(
                Yii::t('common', 'Telegram-бот или бот ВКонтакте успешно подключён!'),
                1,
                1
            );
        }

        return CheckResult::failure(
            Yii::t(
                'common',
                'Ни Telegram-бот, ни бот ВКонтакте не подключены. Перейдите в профиль и подключите одного из ботов.'
            ),
            0,
            1
        );
    }
}
