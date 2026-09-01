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
        // Group chats and channels must never fall back to the configured ID: the
        // same bot token can be used by several projects, each with its own webhook.
        $chatType = (string)ArrayHelper::getValue($update, 'message.recipient.chat_type', '');
        if ($chatType === 'dialog') {
            return $configuredChatId;
        }

        return null;
    }
}
