<?php

namespace common\components\max;

use yii\helpers\ArrayHelper;

/**
 * Resolves the support conversation from MAX webhook payloads.
 */
final class MaxSupportWebhookContext
{
    public static function chatId(array $update, string $configuredChatId): ?string
    {
        $chatId = ArrayHelper::getValue(
            $update,
            'message.recipient.chat_id',
            ArrayHelper::getValue($update, 'chat_id')
        );
        if ($chatId !== null && $chatId !== '') {
            return (string)$chatId;
        }

        if ($configuredChatId === '') {
            return null;
        }

        // In a direct dialog MAX may return recipient.user_id while chat_id is null.
        // The callback still belongs to the configured support conversation because
        // its original message was sent by this bot.
        $chatType = (string)ArrayHelper::getValue($update, 'message.recipient.chat_type', '');
        $isBotCallback = (string)($update['update_type'] ?? '') === 'message_callback'
            && (bool)ArrayHelper::getValue($update, 'message.sender.is_bot', false);
        if ($chatType === 'dialog' || $isBotCallback) {
            return $configuredChatId;
        }

        return null;
    }
}
