<?php

namespace common\tests\unit\components\telegram;

use Codeception\Test\Unit;
use common\components\telegram\TelegramNotificationSettings;

class TelegramNotificationSettingsTest extends Unit
{
    /**
     * @dataProvider enabledValueProvider
     *
     * @param mixed $value
     */
    public function testNormalizesEnabledValues($value, bool $expected): void
    {
        $this->assertSame($expected, TelegramNotificationSettings::normalizeEnabledValue($value));
    }

    public function enabledValueProvider(): array
    {
        return [
            'boolean true' => [true, true],
            'integer one' => [1, true],
            'string one' => ['1', true],
            'missing or empty defaults to enabled' => ['', true],
            'boolean false' => [false, false],
            'integer zero' => [0, false],
            'string zero' => ['0', false],
            'string false' => ['false', false],
            'string off' => ['off', false],
            'string no' => ['no', false],
        ];
    }
}
