<?php

namespace api\controllers\v1;

use Yii;
use common\helpers\ApiPublicCacheTtl;
use yii\web\Response;
use common\models\servers\ServersRadioStation;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с радиостанциями
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="Radio")
 */
class RadioController extends BaseApiController
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
     * Список радиостанций
     * 
     * @OA\Get(
     *     path="/v1/radio/list",
     *     operationId="getRadioList",
     *     tags={"Radio"},
     *     summary="Получить список радиостанций",
     *     description="Публичный метод, авторизация не требуется",
     *     @OA\Response(
     *         response=200,
     *         description="Список радиостанций",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionList()
    {
        // Кэшируем список радиостанций на 10 минут
        $cacheKey = 'api_radio_list';
        $cache = Yii::$app->cache;
        $list = $cache->get($cacheKey);

        if ($list === false) {
            // Получаем радиостанции из базы данных
            $stations = ServersRadioStation::find()
                ->where(['status' => ServersRadioStation::STATUS_ACTIVE])
                ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
                ->all();

            $list = [];
            foreach ($stations as $station) {
                $item = [
                    'name' => $station->name,
                    'url' => $station->url,
                ];
                
                // Добавляем логотип, если есть
                if ($station->logo) {
                    $item['logo'] = $station->getLogoUrl();
                }
                
                $list[] = $item;
            }

            // Сохраняем в кэш на 10 минут (600 секунд)
            $cache->set($cacheKey, $list, ApiPublicCacheTtl::SECONDS);
        }

        return $this->successResponse($list);
    }

    /**
     * Список URL для BoomBox (как frontend ApiController::actionRadioList): { "radioList": "name,url,name,url,..." }.
     */
    public function actionBoomboxList()
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'application/json; charset=UTF-8');

        $stations = ServersRadioStation::find()
            ->where(['status' => ServersRadioStation::STATUS_ACTIVE])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $str = '';
        foreach ($stations as $station) {
            $str .= ',' . $station->name . ',' . $station->url;
        }

        return json_encode(['radioList' => $str === '' ? '' : substr($str, 1)], JSON_UNESCAPED_UNICODE);
    }
}

