<?php

namespace api\controllers\v1;

use Yii;
use common\helpers\SettingsCacheHelper;
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
    const ALLOWED_CATEGORIES = ['design', 'social', 'section', 'metrics', 'site', 'personal_info_ip', 'tgbot'];

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
     *     description="Возвращает публичные настройки по категориям: design, social, section, metrics, site, personal_info_ip, tgbot",
     *     @OA\Parameter(
     *         name="categories",
     *         in="query",
     *         description="Категории настроек (через запятую): design,social,section,metrics,site,personal_info_ip,tgbot",
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
        Yii::$app->response->headers->set('Cache-Control', 'public, max-age=' . SettingsCacheHelper::CACHE_TTL);
        Yii::$app->response->headers->set('Vary', 'Accept-Language');

        $categoriesParam = Yii::$app->request->get('categories');
        $requestedCategories = $categoriesParam ? explode(',', $categoriesParam) : null;

        // Фильтруем только разрешенные категории
        if ($requestedCategories) {
            $categories = array_intersect($requestedCategories, self::ALLOWED_CATEGORIES);
        } else {
            $categories = self::ALLOWED_CATEGORIES;
        }

        $cacheKey = SettingsCacheHelper::cacheKey($categories);
        $settings = Yii::$app->cache->get($cacheKey);

        if ($settings === false) {
            $settings = SettingsCacheHelper::buildPayload($categories);
            Yii::$app->cache->set($cacheKey, $settings, SettingsCacheHelper::CACHE_TTL);
        }

        return $this->successResponse($settings);
    }
}

