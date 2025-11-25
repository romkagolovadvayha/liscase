<?php

namespace common\components\tasks_v2;

use common\models\tasks_v2\TaskV2;
use common\models\user\User;
use Yii;

/**
 * Проверка подключения Telegram-бота
 */
class TelegramConnectedChecker implements TaskCheckerInterface
{
    /**
     * {@inheritdoc}
     */
    public function check(TaskV2 $task, User $user): CheckResult
    {
        if (!empty($user->telegram_chat_id)) {
            return CheckResult::success(
                Yii::t('common', 'Telegram-бот успешно подключен!')
            );
        }

        return CheckResult::failure(
            Yii::t('common', 'Telegram-бот не подключен. Перейдите в раздел настройки и подключите бота.')
        );
    }
}









