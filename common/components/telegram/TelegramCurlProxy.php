<?php

namespace common\components\telegram;

use common\components\proxy\ProxySettings;

/**
 * Прокси для cURL к api.telegram.org с учётом общего и Telegram-выключателя.
 */
final class TelegramCurlProxy
{
    /**
     * @param resource $ch cURL handle
     */
    public static function applyFromSettings($ch): void
    {
        ProxySettings::applyToCurl($ch, ProxySettings::TELEGRAM);
    }
}
