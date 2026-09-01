<?php

namespace common\tests\unit\components\max;

use Codeception\Test\Unit;
use common\components\max\MaxBotApi;

class MaxBotApiMessageBodyTest extends Unit
{
    public function testBuildsImageAndReplyButtonAttachments(): void
    {
        $body = MaxBotApi::messageBody(
            "Новое сообщение\nТикет: https://example.test/1",
            [['text' => '✍️ Ответить', 'payload' => 'support-reply:53304']],
            'https://cdn.example/sticker.png'
        );

        $this->assertSame('image', $body['attachments'][0]['type']);
        $this->assertSame(
            'https://cdn.example/sticker.png',
            $body['attachments'][0]['payload']['url']
        );
        $this->assertSame('inline_keyboard', $body['attachments'][1]['type']);
        $this->assertSame(
            'support-reply:53304',
            $body['attachments'][1]['payload']['buttons'][0][0]['payload']
        );
    }
}
