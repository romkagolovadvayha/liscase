<?php

namespace common\tests\unit\components\openAi;

use Codeception\Test\Unit;
use common\components\openAi\OpenAiSettings;

class OpenAiSettingsTest extends Unit
{
    public function testMissingSettingKeepsBackwardCompatibleEnabledState(): void
    {
        $this->assertTrue(OpenAiSettings::isEnabled(OpenAiSettings::SUPPORT, []));
    }

    public function testDisabledValues(): void
    {
        foreach ([false, 0, '0', 'false', 'off', ''] as $value) {
            $this->assertFalse(OpenAiSettings::isEnabled(OpenAiSettings::SUPPORT, [
                'openAi_' . OpenAiSettings::SUPPORT => $value,
            ]));
        }
    }

    public function testEnabledValues(): void
    {
        foreach ([true, 1, '1', 'true', 'yes', 'on'] as $value) {
            $this->assertTrue(OpenAiSettings::isEnabled(OpenAiSettings::SUPPORT, [
                'openAi_' . OpenAiSettings::SUPPORT => $value,
            ]));
        }
    }

    public function testFeaturesAreIndependent(): void
    {
        $settings = [
            'openAi_' . OpenAiSettings::SUPPORT => '0',
            'openAi_' . OpenAiSettings::DISCORD => '1',
        ];

        $this->assertFalse(OpenAiSettings::isEnabled(OpenAiSettings::SUPPORT, $settings));
        $this->assertTrue(OpenAiSettings::isEnabled(OpenAiSettings::DISCORD, $settings));
    }
}
