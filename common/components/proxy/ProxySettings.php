<?php

namespace common\components\proxy;

use Yii;

/**
 * Общий и независимые выключатели использования прокси.
 */
final class ProxySettings
{
    public const MASTER = 'enabled';
    public const OPENAI_SUPPORT = 'openai_support_enabled';
    public const OPENAI_DISCORD = 'openai_discord_enabled';
    public const OPENAI_CHAT = 'openai_chat_enabled';
    public const OPENAI_QUIZ = 'openai_quiz_enabled';
    public const OPENAI_COMMENT = 'openai_comment_enabled';
    public const OPENAI_VK_POST = 'openai_vk_post_enabled';
    public const OPENAI_TELEGRAM_POST = 'openai_telegram_post_enabled';
    public const OPENAI_TRANSLATION = 'openai_translation_enabled';
    public const TELEGRAM = 'telegram_enabled';
    public const VIDEO_METADATA = 'video_metadata_enabled';
    public const MAP = 'map_enabled';

    public static function isEnabled(string $feature, ?array $settings = null): bool
    {
        if ($settings === null) {
            if (Yii::$app === null || !Yii::$app->has('settings')) {
                return true;
            }

            try {
                $settings = Yii::$app->settings->getSettings();
            } catch (\Throwable $e) {
                Yii::warning('Не удалось прочитать выключатели прокси: ' . $e->getMessage(), __METHOD__);
                return false;
            }
        }

        return self::settingValue($settings, self::MASTER)
            && self::settingValue($settings, $feature);
    }

    /**
     * @return array{http:string,https:string}|null
     */
    public static function getGuzzleProxy(string $feature): ?array
    {
        if (!self::isEnabled($feature) || Yii::$app === null || !Yii::$app->has('settings')) {
            return null;
        }

        $proxyIp = trim((string)Yii::$app->settings->get('proxy_ip'));
        if ($proxyIp === '') {
            return null;
        }

        $scheme = 'http://';
        $endpoint = $proxyIp;
        if (preg_match('#^(https?://)(.+)$#i', $proxyIp, $matches)) {
            $scheme = $matches[1];
            $endpoint = $matches[2];
        }

        $username = (string)Yii::$app->settings->get('proxy_username');
        $password = (string)Yii::$app->settings->get('proxy_password');
        $credentials = $username !== ''
            ? rawurlencode($username) . ':' . rawurlencode($password) . '@'
            : '';
        $proxy = $scheme . $credentials . $endpoint;

        return ['http' => $proxy, 'https' => $proxy];
    }

    public static function applyToGuzzleOptions(array &$options, string $feature): void
    {
        if (!self::isEnabled($feature)) {
            // Не даём Guzzle/libcurl подхватить HTTP_PROXY/HTTPS_PROXY из окружения.
            $options['proxy'] = '';
            $options['curl'][CURLOPT_PROXY] = '';
            $options['curl'][CURLOPT_NOPROXY] = '*';
            return;
        }

        $proxy = self::getGuzzleProxy($feature);
        if ($proxy !== null) {
            $options['proxy'] = $proxy;
        }
    }

    /**
     * @param resource $ch cURL handle
     */
    public static function applyToCurl($ch, string $feature): void
    {
        if (!self::isEnabled($feature)) {
            curl_setopt($ch, CURLOPT_PROXY, '');
            curl_setopt($ch, CURLOPT_NOPROXY, '*');
            return;
        }

        if (Yii::$app === null || !Yii::$app->has('settings')) {
            return;
        }

        $proxyIp = trim((string)Yii::$app->settings->get('proxy_ip'));
        if ($proxyIp === '') {
            return;
        }

        curl_setopt($ch, CURLOPT_PROXY, $proxyIp);
        $username = (string)Yii::$app->settings->get('proxy_username');
        $password = (string)Yii::$app->settings->get('proxy_password');
        if ($username !== '') {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $username . ':' . $password);
        }
    }

    private static function settingValue(array $settings, string $code): bool
    {
        $key = 'proxy_' . $code;
        if (!array_key_exists($key, $settings)) {
            return true;
        }

        $value = $settings[$key];
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
}
