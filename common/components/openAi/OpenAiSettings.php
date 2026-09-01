<?php

namespace common\components\openAi;

use Yii;

/**
 * Независимые выключатели обращений к OpenAI.
 *
 * Проверка находится рядом с HTTP-клиентами, поэтому она действует и для
 * уже поставленных в очередь заданий. Если миграция ещё не применена,
 * сохраняем прежнее поведение и считаем OpenAI включённым.
 */
final class OpenAiSettings
{
    public const SUPPORT = 'support_enabled';
    public const DISCORD = 'discord_enabled';
    public const CHAT_MODERATION = 'chat_enabled';
    public const QUIZ = 'quiz_enabled';
    public const TRANSLATION = 'translation_enabled';
    public const CONTENT = 'content_enabled';
    public const COMMENT = 'comment_enabled';
    public const VK_POST = 'vk_post_enabled';
    public const TELEGRAM_POST = 'telegram_post_enabled';

    public static function isEnabled(string $feature, ?array $settings = null): bool
    {
        if ($settings === null) {
            if (Yii::$app === null || !Yii::$app->has('settings')) {
                return true;
            }

            try {
                $settings = Yii::$app->settings->getSettings();
            } catch (\Throwable $e) {
                Yii::warning('Не удалось прочитать выключатель OpenAI: ' . $e->getMessage(), __METHOD__);
                return false;
            }
        }

        $settingKey = 'openAi_' . $feature;
        if (!array_key_exists($settingKey, $settings)) {
            return true;
        }

        $value = $settings[$settingKey];
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function ensureEnabled(string $feature): void
    {
        if (!self::isEnabled($feature)) {
            throw new \RuntimeException('Этот сценарий ChatGPT/OpenAI отключён в настройках сайта.');
        }
    }
}
