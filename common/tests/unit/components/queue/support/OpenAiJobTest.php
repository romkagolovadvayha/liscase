<?php

namespace common\tests\unit\components\queue\support;

use Codeception\Test\Unit;
use common\components\queue\support\OpenAiJob;

class OpenAiJobTest extends Unit
{
    public function testReplyWithoutMarkerIsLeftUntouched(): void
    {
        $result = OpenAiJob::parseReply('<b>Готово</b>');

        $this->assertFalse($result['handoff']);
        $this->assertSame('<b>Готово</b>', $result['message']);
    }

    public function testHandoffMarkerIsRemovedFromVisibleReply(): void
    {
        $result = OpenAiJob::parseReply(
            'Проверку должен продолжить сотрудник.<br>Ожидайте ответ. [[staff_handoff]]'
        );

        $this->assertTrue($result['handoff']);
        $this->assertSame(
            'Проверку должен продолжить сотрудник.<br>Ожидайте ответ.',
            $result['message']
        );
    }

    public function testLegacyUnknownTriggersSilentHandoff(): void
    {
        $result = OpenAiJob::parseReply('unknown');

        $this->assertTrue($result['handoff']);
        $this->assertSame('', $result['message']);
    }
}
