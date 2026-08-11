<?php

namespace common\helpers;

use Yii;
use common\models\site\SiteSetting;

/**
 * Формирование payload настроек для API /v1/settings.
 * Используется в API при cache miss и в console для прогрева кэша (метрики и прочие категории).
 */
class SettingsCacheHelper
{
    /** Категории по умолчанию (как в SettingsController::ALLOWED_CATEGORIES). */
    public const DEFAULT_CATEGORIES = ['design', 'colors', 'social', 'section', 'metrics', 'site', 'personal_info_ip', 'tgbot', 'clans', 'openAi', 'banner_side', 'media'];

    /**
     * Категории с узким публичным контрактом. В openAi фронтенду нужны только
     * имя и аватар служебного пользователя; инструкции и knowledge base
     * никогда не должны попадать в публичный ответ.
     */
    public const PUBLIC_KEYS_BY_CATEGORY = [
        'openAi' => ['avatar', 'username', 'user_name'],
        'tgbot' => ['login'],
    ];

    /** TTL кэша в секундах (публичный payload настроек). */
    public const CACHE_TTL = ApiPublicCacheTtl::SECONDS;

    /** Исключаемые паттерны ключей (секретные данные). */
    public const EXCLUDED_PATTERNS = [
        '/_token$/i',
        '/_secret$/i',
        '/_password$/i',
        '/_apiKey$/i',
        '/^s3_/i',
        '/payment_.*_secret/i',
        '/vk_user_token/i',
        '/telegramChannel_token/i',
    ];

    /**
     * Собрать payload настроек по категориям (как SettingsController::loadSettings).
     *
     * @param array $categories
     * @return array
     */
    public static function buildPayload(array $categories): array
    {
        $settings = [];
        foreach ($categories as $category) {
            $categorySettings = SiteSetting::find()
                ->where(['category' => $category])
                ->all();

            $settings[$category] = [];
            foreach ($categorySettings as $setting) {
                $key = $setting->code;
                $fullKey = $category . '_' . $key;

                if (isset(self::PUBLIC_KEYS_BY_CATEGORY[$category])
                    && !in_array($key, self::PUBLIC_KEYS_BY_CATEGORY[$category], true)) {
                    continue;
                }

                // Секретные поля (тип password) никогда не попадают в публичный API
                if ($setting->type === 'password') {
                    continue;
                }

                if (self::isSecretKey($fullKey)) {
                    continue;
                }
                $value = self::formatValue($setting);
                if ($category === 'section') {
                    $settings[$category][$fullKey] = $value;
                } else {
                    $settings[$category][$key] = $value;
                }
            }
        }
        return $settings;
    }

    public static function cacheKey(array $categories): string
    {
        sort($categories);
        return 'api_settings3_' . md5(implode(',', $categories));
    }

    /**
     * Очистить кэш API настроек (вызывать при сохранении настроек в backend).
     * Удаляет ключи для полного набора категорий и для каждой категории по отдельности.
     */
    public static function clearApiSettingsCache(): void
    {
        $cache = Yii::$app->cache;
        $categories = self::DEFAULT_CATEGORIES;
        $cache->delete(self::cacheKey($categories));
        foreach ($categories as $category) {
            $cache->delete(self::cacheKey([$category]));
        }
    }

    public static function isSecretKey(string $key): bool
    {
        foreach (self::EXCLUDED_PATTERNS as $pattern) {
            if (preg_match($pattern, $key)) {
                return true;
            }
        }
        return false;
    }

    public static function formatValue(SiteSetting $setting)
    {
        $value = $setting->getValue();
        switch ($setting->type) {
            case 'checkbox':
                return (bool) $value;
            case 'number':
                if (is_numeric($value)) {
                    return strpos($value, '.') !== false ? (float) $value : (int) $value;
                }
                return 0;
            case 'image':
            case 'video':
                return $value;
            case 'text':
            case 'longtext':
            case 'password':
            default:
                return (string) $value;
        }
    }
}
