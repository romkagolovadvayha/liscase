<?php

namespace common\components\telegram\foreignSystem;

use common\components\telegram\SupportTelegramReplyService;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * Бот уведомлений модерации ({@see TelegramSupport}, настройка tgbotSupportAlert_token).
 * Inline-кнопки «Принять/Отклонить» должны попадать на вебхук этого бота, иначе callback не обработается.
 *
 * URL: <apiPublicUrl>/v1/webhook/telegram/support/{token} или …/telegram/{token}, если токен совпадает с personal
 */
class SupportAlertBotSystem extends PersonalBotSystem
{
    /**
     * В группе поддержки обычный текст обрабатывается только как начатый через кнопку ответ.
     * В личном чате (когда personal/support используют один токен) сохраняются команды PersonalBotSystem.
     *
     * @param array $message
     * @return array|string|null
     */
    public function executeCommand($message)
    {
        $chatId = ArrayHelper::getValue($message, 'chat.id');
        if (SupportTelegramReplyService::isSupportChat($chatId)) {
            return (new SupportTelegramReplyService())->handleMessage($message);
        }

        return parent::executeCommand($message);
    }

    /**
     * @param int|string $chatId
     * @param string $buttonValue
     * @param array $callbackQuery
     * @return array|string|null
     */
    public function executeCallBack($chatId, $buttonValue, $callbackQuery = [])
    {
        $ticketNumber = SupportTelegramReplyService::ticketNumberFromCallback((string)$buttonValue);
        if ($ticketNumber !== null) {
            return (new SupportTelegramReplyService())->beginReply(
                $chatId,
                ArrayHelper::getValue($callbackQuery, 'from.id'),
                $ticketNumber,
                ArrayHelper::getValue($callbackQuery, 'from.username')
            );
        }

        return parent::executeCallBack($chatId, $buttonValue, $callbackQuery);
    }

    /**
     * @return string
     */
    public function getTelegramToken()
    {
        return (string)Yii::$app->settings->get('tgbotSupportAlert_token');
    }
}
