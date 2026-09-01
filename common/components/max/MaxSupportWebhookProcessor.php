<?php

namespace common\components\max;

use common\components\support\SupportReplyCallback;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * Обрабатывает события message_callback и message_created от MAX.
 */
final class MaxSupportWebhookProcessor
{
    private const CALLBACK_CACHE_PREFIX = 'max_support_callback_processed_v1:';
    private const CALLBACK_CACHE_TTL = 5 * 60;

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
        $chatId = $this->messageChatId($update);
        if (!$this->replies->isSupportChat($chatId)) {
            // Один MAX-бот может использоваться несколькими проектами. Их webhook
            // получают один callback, но отвечает только владелец текущего чата.
            return;
        }

        $callbackCacheKey = $this->callbackCacheKey($callbackId);
        if ($callbackCacheKey !== null && !Yii::$app->cache->add(
            $callbackCacheKey,
            1,
            self::CALLBACK_CACHE_TTL
        )) {
            return;
        }

        try {
            $this->processUniqueCallback($update, $chatId, $ticketNumber, $callbackId);
        } catch (\Throwable $e) {
            if ($callbackCacheKey !== null) {
                Yii::$app->cache->delete($callbackCacheKey);
            }

            throw $e;
        }
    }

    /**
     * @param int|string $chatId
     */
    private function processUniqueCallback(
        array $update,
        $chatId,
        int $ticketNumber,
        string $callbackId
    ): void
    {
        $operatorMaxId = ArrayHelper::getValue($update, 'callback.user.user_id');
        $result = $this->replies->beginReply($chatId, $operatorMaxId, $ticketNumber);

        if ($callbackId !== '') {
            $notice = (string)($result['callbackNotice'] ?? $result['message'] ?? 'Готово');
            $this->api->answerCallback($callbackId, $notice);
        }
        if ($chatId !== null && !empty($result['message'])) {
            $this->api->sendMessage($chatId, (string)$result['message']);
        }
    }

    private function callbackCacheKey(string $callbackId): ?string
    {
        return $callbackId === ''
            ? null
            : self::CALLBACK_CACHE_PREFIX . hash('sha256', $callbackId);
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
