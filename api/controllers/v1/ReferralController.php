<?php

namespace api\controllers\v1;

use Yii;
use common\helpers\ApiPublicCacheTtl;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с реферальной системой
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="Referral")
 */
class ReferralController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // Все методы публичные, JWT не требуется
        return $behaviors;
    }

    /**
     * Получение информации о реферальной системе
     * 
     * @OA\Get(
     *     path="/v1/referral",
     *     operationId="getReferralInfo",
     *     tags={"Referral"},
     *     summary="Получить информацию о реферальной системе",
     *     description="Возвращает настройки реферальной системы и персональную ссылку (если пользователь авторизован)",
     *     @OA\Response(
     *         response=200,
     *         description="Информация о реферальной системе",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionIndex()
    {
        if (!Yii::$app->settings->get('section_referral')) {
            return $this->errorResponse('REFERRAL_DISABLED', 'Реферальная система отключена', [], 404);
        }

        // Кэшируем настройки на 1 час
        $cacheKey = 'api_referral_settings';
        $cache = Yii::$app->cache;
        $settings = $cache->get($cacheKey);

        if ($settings === false) {
            $settings = [
                'referral_percent' => (float)Yii::$app->settings->get('referral_percent', 0),
                'referral_bonus' => (float)Yii::$app->settings->get('referral_bonus', 0),
                'referral_skin' => (bool)Yii::$app->settings->get('referral_skin', false),
                'referral_minSum' => (float)Yii::$app->settings->get('referral_minSum', 0),
                'referral_maxSum' => (float)Yii::$app->settings->get('referral_maxSum', 0),
            ];

            // Сохраняем в кэш на 1 час (3600 секунд)
            $cache->set($cacheKey, $settings, ApiPublicCacheTtl::SECONDS);
        }

        $data = [
            'settings' => $settings,
        ];

        // Если пользователь авторизован, добавляем его персональную ссылку
        $user = Yii::$app->user->identity;
        if ($user) {
            $refCode = $user->ref_code ?? '';
            $baseUrl = Yii::$app->params['baseUrl'] ?? (Yii::$app->params['homePage'] ?? 'http://localhost');
            // Убираем api. из URL если есть
            $baseUrl = str_replace('api.', '', $baseUrl);
            $partnerLink = !empty($refCode) ? ($baseUrl . '/p/' . $refCode) : '';
            
            $data['user'] = [
                'referral_link' => $partnerLink,
                'ref_code' => $refCode,
            ];
        }

        return $this->successResponse($data);
    }
}

