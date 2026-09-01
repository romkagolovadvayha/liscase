<?php

namespace common\components\max;

use common\components\support\SupportReplyCallback;
use yii\helpers\ArrayHelper;

/**
 * Обрабатывает события message_callback и message_created от MAX.
 */
final class MaxSupportWebhookProcessor
{
    private MaxBotApi $api;
    private MaxSupportReplyService $replies;

    public function __construct(?MaxBotApi $api = null, ?MaxSupportReplyService $replies = null)
    {
        $this->api = $api ?? new MaxBotApi();
        $this->replies = $replies ?? new MaxSupportReplyService();
    }

    public function process(array $update): void
    {
        $updateType = (string)($update['update_type'] ?? '');
        if ($updateType === 'bot_added') {
            $this->processBotAdded($update);

            return;
        }
        if ($updateType === 'message_callback') {
            $this->processCallback($update);

            return;
        }
        if ($updateType === 'message_created') {
            $this->processMessage($update);
        }
    }

    private function processBotAdded(array $update): void
    {
        $settings = new MaxSupportSettings();
        if ($settings->chatId() !== '' || (bool)($update['is_channel'] ?? false)) {
            return;
        }

        $chatId = ArrayHelper::getValue($update, 'chat_id');
        if ($chatId === null || $chatId === '') {
            return;
        }

        if (!\Yii::$app->settings->set('maxSupport_chatId', (string)$chatId)) {
            return;
        }
        $this->api->sendMessage(
            $chatId,
            "✅ Этот MAX-чат привязан к поддержке.\nID чата: {$chatId}"
        );
    }

    private function processCallback(array $update): void
    {
        $payload = (string)ArrayHelper::getValue($update, 'callback.payload', '');
        $ticketNumber = SupportReplyCallback::parse($payload);
        if ($ticketNumber === null) {
            return;
        }

        $callbackId = (string)ArrayHelper::getValue($update, 'callback.callback_id', '');
        $operatorMaxId = ArrayHelper::getValue($update, 'callback.user.user_id');
        $chatId = $this->messageChatId($update);
        $result = $this->replies->beginReply($chatId, $operatorMaxId, $ticketNumber);

        if ($callbackId !== '') {
            $notice = (string)($result['callbackNotice'] ?? $result['message'] ?? 'Готово');
            $this->api->answerCallback($callbackId, $notice);
        }
        if ($chatId !== null && !empty($result['message'])) {
            $this->api->sendMessage($chatId, (string)$result['message']);
        }
    }

    private function processMessage(array $update): void
    {
        if ((bool)ArrayHelper::getValue($update, 'message.sender.is_bot', false)) {
            return;
        }

        $chatId = $this->messageChatId($update);
        $operatorMaxId = ArrayHelper::getValue($update, 'message.sender.user_id');
        $text = (string)ArrayHelper::getValue($update, 'message.body.text', '');
        $result = $this->replies->handleMessage($chatId, $operatorMaxId, $text);
        $responseChatId = $result['chatId'] ?? $chatId;
        if ($responseChatId !== null && $responseChatId !== '' && !empty($result['message'])) {
            $this->api->sendMessage($responseChatId, (string)$result['message']);
        }
    }

    /**
     * @return int|string|null
     */
    private function messageChatId(array $update)
    {
        return MaxSupportWebhookContext::chatId(
            $update,
            (new MaxSupportSettings())->chatId()
        );
    }
}
