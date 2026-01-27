<?php

namespace api\controllers\v1;

use Yii;
use common\models\site\SiteSetting;
use OpenApi\Annotations as OA;

/**
 * Контроллер для получения публичных настроек фронтенда
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="Settings")
 */
class SettingsController extends BaseApiController
{
    /**
     * Список разрешенных категорий для фронтенда
     */
    const ALLOWED_CATEGORIES = ['design', 'social', 'section', 'metrics', 'site', 'personal_info_ip'];

    /**
     * Список исключаемых ключей (секретные данные)
     */
    const EXCLUDED_PATTERNS = [
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
     * GET настройки для фронтенда
     * 
     * @OA\Get(
     *     path="/v1/settings",
     *     operationId="getSettings",
     *     tags={"Settings"},
     *     summary="Получить публичные настройки сайта",
     *     description="Возвращает публичные настройки по категориям: design, social, section, metrics, site, personal_info_ip",
     *     @OA\Parameter(
     *         name="categories",
     *         in="query",
     *         description="Категории настроек (через запятую): design,social,section,metrics,site,personal_info_ip",
     *         required=false,
     *         @OA\Schema(type="string", example="design,social")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Настройки",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(property="data", type="object",
     *                     @OA\Property(property="design", type="object"),
     *                     @OA\Property(property="social", type="object"),
     *                     @OA\Property(property="section", type="object"),
     *                     @OA\Property(property="metrics", type="object")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function actionIndex()
    {
        $categoriesParam = Yii::$app->request->get('categories');
        $requestedCategories = $categoriesParam ? explode(',', $categoriesParam) : null;

        // Фильтруем только разрешенные категории
        if ($requestedCategories) {
            $categories = array_intersect($requestedCategories, self::ALLOWED_CATEGORIES);
        } else {
            $categories = self::ALLOWED_CATEGORIES;
        }

        // Получаем настройки из кэша
        $cacheKey = 'api_settings_' . md5(implode(',', $categories));
        $settings = Yii::$app->cache->get($cacheKey);

        if ($settings === false) {
            $settings = $this->loadSettings($categories);
            // Кэшируем на 1 час
            Yii::$app->cache->set($cacheKey, $settings, 3600);
        }

        return $this->successResponse($settings);
    }

    /**
     * Загрузка настроек из базы данных
     * 
     * @param array $categories Список категорий
     * @return array
     */
    protected function loadSettings($categories)
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

                // Пропускаем секретные данные
                if ($this->isSecretKey($fullKey)) {
                    continue;
                }

                // Форматируем значение согласно типу
                $value = $this->formatValue($setting);

                // Для категорий section используем только ключ code, без префикса category
                if ($category === 'section') {
                    $settings[$category][$fullKey] = $value; // Оставляем полный ключ для section
                } else {
                    $settings[$category][$key] = $value;
                }
            }
        }

        return $settings;
    }

    /**
     * Проверка, является ли ключ секретным
     * 
     * @param string $key
     * @return bool
     */
    protected function isSecretKey($key)
    {
        foreach (self::EXCLUDED_PATTERNS as $pattern) {
            if (preg_match($pattern, $key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Форматирование значения согласно типу настройки
     * 
     * @param SiteSetting $setting
     * @return mixed
     */
    protected function formatValue($setting)
    {
        $value = $setting->getValue();

        switch ($setting->type) {
            case 'checkbox':
                return (bool)$value;
            
            case 'number':
                if (is_numeric($value)) {
                    return strpos($value, '.') !== false ? (float)$value : (int)$value;
                }
                return 0;
            
            case 'image':
            case 'video':
                // getValue() уже возвращает полный URL для image/video
                return $value;
            
            case 'text':
            case 'longtext':
            default:
                return (string)$value;
        }
    }
}

