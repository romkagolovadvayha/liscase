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
     * Список разрешенных категорий для фронтенда.
     * metrics — счётчики (Яндекс.Метрика, gtag и т.д.), поле code — произвольный HTML/скрипты для вставки на Next.
     */
    const ALLOWED_CATEGORIES = ['design', 'colors', 'social', 'section', 'metrics', 'site', 'personal_info_ip', 'tgbot', 'clans', 'openAi', 'banner_side', 'media'];

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
     *     description="Возвращает публичные настройки по категориям: design, colors, social, section, metrics, site, personal_info_ip, tgbot, clans, openAi, banner_side, media (баннеры партнёров: banner_1, banner_2; секреты openAi не отдаются)",
     *     @OA\Parameter(
     *         name="categories",
     *         in="query",
     *         description="Категории настроек (через запятую): design,colors,social,section,metrics,site,personal_info_ip,tgbot,clans,openAi,banner_side,media",
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
     *                     @OA\Property(property="colors", type="object", description="Цвета и оформление: categories-is-image и др."),
     *                     @OA\Property(property="social", type="object"),
     *                     @OA\Property(property="section", type="object"),
     *                     @OA\Property(property="metrics", type="object"),
     *                     @OA\Property(property="clans", type="object", description="gold, silver, bronze — подиум; background — фон страниц кланов"),
     *                     @OA\Property(property="openAi", type="object", description="Публичные поля бота: avatar, username"),
     *                     @OA\Property(property="banner_side", type="object", description="Баннер сайдбара: image, en_image, en_link, ru_link"),
     *                     @OA\Property(property="media", type="object", description="Медиа/партнёры: banner_1, banner_2 (URL промо)")
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

    /**
     * Список определений site_settings для синхронизации между инсталляциями (консоль drop-parser/new-settings).
     * Формат как у бывшего GET https://prostoj.store/api/settings: массив в data с полями name, code, category, type, system_code, is_translate.
     *
     * @OA\Get(
     *     path="/v1/settings/site-definitions",
     *     operationId="getSettingsSiteDefinitions",
     *     tags={"Settings"},
     *     summary="Определения настроек сайта для репликации схемы (без значений)",
     *     @OA\Response(response=200, description="success + data: массив объектов")
     * )
     */
    public function actionSiteDefinitions()
    {
        /** @var SiteSetting[] $list */
        $list = SiteSetting::find()
            ->cache(60)
            ->all();

        $items = [];
        foreach ($list as $item) {
            $items[] = [
                'name' => $item->name,
                'code' => $item->code,
                'category' => $item->category,
                'type' => $item->type,
                'system_code' => $item->category . '_' . $item->code,
                'is_translate' => $item->is_translate,
            ];
        }

        return $this->successResponse($items);
    }
}

