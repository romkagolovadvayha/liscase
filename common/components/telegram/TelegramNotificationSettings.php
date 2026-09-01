<?php

namespace common\components\telegram;

use Yii;

/**
 * Reads per-bot notification switches while keeping notifications enabled
 * when a newly deployed setting has not been added to the database yet.
 */
final class TelegramNotificationSettings
{
    public static function isEnabled(string $category): bool
    {
        if (Yii::$app === null || !Yii::$app->has('settings')) {
            return true;
        }

        $settings = Yii::$app->settings->getSettings();
        $key = $category . '_enabled';

        if (!is_array($settings) || !array_key_exists($key, $settings)) {
            return true;
        }

        return self::normalizeEnabledValue($settings[$key]);
    }

    /**
     * @param mixed $value
     */
    public static function normalizeEnabledValue($value): bool
    {
        if ($value === false || $value === 0 || $value === '0') {
            return false;
        }

        if (is_string($value)) {
            return !in_array(strtolower(trim($value)), ['false', 'off', 'no'], true);
        }

        return true;
    }
}
