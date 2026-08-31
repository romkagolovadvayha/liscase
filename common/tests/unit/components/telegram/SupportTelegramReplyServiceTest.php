<?php

namespace common\tests\unit\components\telegram;

use Codeception\Test\Unit;
use common\components\telegram\SupportTelegramReplyService;

class SupportTelegramReplyServiceTest extends Unit
{
    public function testBuildsAndParsesReplyCallback(): void
    {
        $callback = SupportTelegramReplyService::callbackData(54321);

        $this->assertSame('support-reply:54321', $callback);
        $this->assertSame(54321, SupportTelegramReplyService::ticketNumberFromCallback($callback));
    }

    /**
     * @dataProvider invalidCallbackProvider
     */
    public function testRejectsInvalidReplyCallback(string $callback): void
    {
        $this->assertNull(SupportTelegramReplyService::ticketNumberFromCallback($callback));
    }

    public function invalidCallbackProvider(): array
    {
        return [
            [''],
            ['support-reply:not-a-number'],
            ['support-reply:43242'],
            ['other-action:54321'],
            ['support-reply:54321:extra'],
        ];
    }
}
