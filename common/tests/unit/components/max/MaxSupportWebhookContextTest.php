<?php

namespace common\tests\unit\components\max;

use Codeception\Test\Unit;
use common\components\max\MaxSupportWebhookContext;

class MaxSupportWebhookContextTest extends Unit
{
    public function testUsesRecipientChatIdForGroupMessage(): void
    {
        $update = [
            'update_type' => 'message_created',
            'message' => [
                'recipient' => ['chat_id' => -12345, 'chat_type' => 'chat'],
            ],
        ];

        $this->assertSame('-12345', MaxSupportWebhookContext::chatId($update, '-12345'));
    }

    public function testUsesConfiguredChatForDirectMessageWithoutChatId(): void
    {
        $update = [
            'update_type' => 'message_created',
            'message' => [
                'sender' => ['user_id' => 777],
                'recipient' => ['chat_id' => null, 'chat_type' => 'dialog', 'user_id' => 999],
            ],
        ];

        $this->assertSame('54321', MaxSupportWebhookContext::chatId($update, '54321'));
    }

    public function testUsesConfiguredChatForBotCallbackWithoutChatId(): void
    {
        $update = [
            'update_type' => 'message_callback',
            'callback' => ['user' => ['user_id' => 777]],
            'message' => [
                'sender' => ['is_bot' => true],
                'recipient' => ['chat_id' => null],
            ],
        ];

        $this->assertSame('54321', MaxSupportWebhookContext::chatId($update, '54321'));
    }

    public function testDoesNotGuessAGroupChat(): void
    {
        $update = [
            'update_type' => 'message_created',
            'message' => [
                'recipient' => ['chat_id' => null, 'chat_type' => 'chat'],
            ],
        ];

        $this->assertNull(MaxSupportWebhookContext::chatId($update, '54321'));
    }
}
