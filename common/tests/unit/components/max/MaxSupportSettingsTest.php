<?php

namespace common\tests\unit\components\max;

use Codeception\Test\Unit;
use common\components\max\MaxSupportSettings;

class MaxSupportSettingsTest extends Unit
{
    public function testParsesJsonOperatorMap(): void
    {
        $map = MaxSupportSettings::parseOperatorMap('{"123456789": 42, "987654321": "77"}');

        $this->assertSame([
            '123456789' => 42,
            '987654321' => 77,
        ], $map);
    }

    public function testParsesLineOperatorMapAndIgnoresInvalidRows(): void
    {
        $map = MaxSupportSettings::parseOperatorMap(<<<'MAP'
# MAX ID: ID пользователя сайта
123456789:42
987654321 = 77
invalid
MAP);

        $this->assertSame([
            '123456789' => 42,
            '987654321' => 77,
        ], $map);
    }

    public function testParsesListOfOperatorObjects(): void
    {
        $map = MaxSupportSettings::parseOperatorMap(
            '[{"maxId":"123456789","userId":42},{"max_user_id":"987654321","user_id":77}]'
        );

        $this->assertSame([
            '123456789' => 42,
            '987654321' => 77,
        ], $map);
    }
}
