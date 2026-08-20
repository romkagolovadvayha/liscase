<?php

namespace common\tests\unit\models\servers;

use common\models\servers\Servers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServersCommandsTest extends TestCase
{
    #[DataProvider('commandValues')]
    public function testNormalizeCommands($value, array $expected): void
    {
        self::assertSame($expected, Servers::normalizeCommands($value));
    }

    public static function commandValues(): array
    {
        return [
            'JSON column value' => [
                ['report', ' wipe ', 'report', ''],
                ['report', 'wipe'],
            ],
            'comma-separated form value' => [
                'report, wipe, store',
                ['report', 'wipe', 'store'],
            ],
            'newline-separated form value' => [
                "report\nwipe\r\nstore",
                ['report', 'wipe', 'store'],
            ],
            'legacy JSON string' => [
                '["report","wipe"]',
                ['report', 'wipe'],
            ],
            'empty value' => [null, []],
            'unsupported value' => [new \stdClass(), []],
        ];
    }
}
