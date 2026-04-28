<?php

namespace common\components\telegram\foreignSystem;

use Yii;

/**
 * Бот уведомлений модерации ({@see TelegramSupport}, настройка tgbotSupportAlert_token).
 * Inline-кнопки «Принять/Отклонить» должны попадать на вебхук этого бота, иначе callback не обработается.
 *
 * URL: <apiPublicUrl>/v1/webhook/telegram/support/{token} или …/telegram/{token}, если токен совпадает с personal
 */
class SupportAlertBotSystem extends PersonalBotSystem
{
    /**
     * @return string
     */
    public function getTelegramToken()
    {
        return (string)Yii::$app->settings->get('tgbotSupportAlert_token');
    }
}
