<?php

namespace common\components\telegram;

use common\components\telegram\foreignSystem\AbstractSystem;
use common\components\telegram\foreignSystem\AbstractSystemBots;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

/**
 * Обработка входящего update Telegram Bot API (вебхук).
 * Используется фронтовым модулем webhook и публичным API.
 */
final class TelegramWebhookProcessor
{
    /**
     * @param AbstractSystem|AbstractSystemBots $system
     */
    public static function tokenMatches(object $system, string $urlToken): bool
    {
        if (!($system instanceof AbstractSystem) && !($system instanceof AbstractSystemBots)) {
            return false;
        }

        return $urlToken === $system->getTelegramToken();
    }

    /**
     * Разбор тела запроса (токен уже сверен). Невалидный JSON / не-message update — тихий выход (Telegram ждёт 200).
     *
     * @param AbstractSystem|AbstractSystemBots $system
     */
    public static function process(object $system, string $rawBody): void
    {
        if (!($system instanceof AbstractSystem) && !($system instanceof AbstractSystemBots)) {
            return;
        }

        /** @var TelegramApiHelper $bot */
        $bot = $system->getTelegramBot();

        if ($rawBody === '') {
            return;
        }

        try {
            $inputParams = Json::decode($rawBody);
        } catch (\Throwable $e) {
            return;
        }
        if (!is_array($inputParams)) {
            return;
        }

        $callBack = ArrayHelper::getValue($inputParams, 'callback_query', []);

        if (!empty($callBack)) {
            $buttonValue = ArrayHelper::getValue($callBack, 'data');
            $message     = ArrayHelper::getValue($callBack, 'message');
            $chat        = ArrayHelper::getValue($message, 'chat');
            $textMessage = ArrayHelper::getValue($message, 'text');
            $caption = ArrayHelper::getValue($message, 'caption');
            if (empty($buttonValue) || empty($chat)) {
                return;
            }

            $answerMessage = $system->executeCallBack($chat['id'], $buttonValue);
            if (!empty($answerMessage['editMessageReplyMarkup'])) {
                $bot->editMessageReplyMarkup($chat['id'], $message['message_id'], $answerMessage['buttons']);
            } elseif (!empty($answerMessage['message'])) {
                $bot->sendMessage($chat['id'], $answerMessage['message'], $answerMessage['buttons']);
            } elseif (!empty($textMessage)) {
                $textMessage .= "\n\n" . $answerMessage;
                $bot->editMessageText($chat['id'], $message['message_id'], $textMessage);
            } elseif (!empty($caption)) {
                $caption .= "\n\n" . $answerMessage;
                $bot->editMessageCaption($chat['id'], $message['message_id'], $caption);
            }
        } else {
            $message = ArrayHelper::getValue($inputParams, 'message');
            $chat    = ArrayHelper::getValue($message, 'chat');
            if (empty($chat)) {
                return;
            }

            $answerMessage = $system->executeCommand($message);
            if (!empty($answerMessage['message'])) {
                $bot->sendMessage($chat['id'], $answerMessage['message'], $answerMessage['buttons']);
            } elseif (!empty($answerMessage)) {
                $bot->sendMessage($chat['id'], $answerMessage);
            }
        }
    }
}
