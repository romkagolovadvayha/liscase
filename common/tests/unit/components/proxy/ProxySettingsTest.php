<?php

namespace common\tests\unit\components\proxy;

use Codeception\Test\Unit;
use common\components\proxy\ProxySettings;

class ProxySettingsTest extends Unit
{
    public function testMissingSettingsKeepBackwardCompatibleEnabledState(): void
    {
        $this->assertTrue(ProxySettings::isEnabled(ProxySettings::TELEGRAM, []));
    }

    public function testMasterSwitchDisablesEveryFeature(): void
    {
        $settings = [
            'proxy_enabled' => '0',
            'proxy_telegram_enabled' => '1',
        ];

        $this->assertFalse(ProxySettings::isEnabled(ProxySettings::TELEGRAM, $settings));
        $this->assertFalse(ProxySettings::isEnabled(ProxySettings::OPENAI_SUPPORT, $settings));
    }

    public function testFeatureSwitchesAreIndependent(): void
    {
        $settings = [
            'proxy_enabled' => '1',
            'proxy_telegram_enabled' => '0',
            'proxy_openai_support_enabled' => '1',
        ];

        $this->assertFalse(ProxySettings::isEnabled(ProxySettings::TELEGRAM, $settings));
        $this->assertTrue(ProxySettings::isEnabled(ProxySettings::OPENAI_SUPPORT, $settings));
    }
}
