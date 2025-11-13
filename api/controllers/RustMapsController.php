<?php

namespace api\controllers;

use common\models\box\DropImage;
use common\models\map\Map;
use common\models\map\MapList;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

class RustMapsController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['webhook'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'webhook' => ['POST'],
                ],
            ],
        ]);
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        Yii::$app->response->format = Response::FORMAT_JSON;

        return true;
    }

    public function actionWebhook()
    {
        $rawBody = Yii::$app->request->rawBody;
        $payload = null;

        if ($rawBody !== '' && $rawBody !== null) {
            $payload = json_decode($rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Yii::warning('RustMaps webhook received invalid JSON: ' . json_last_error_msg(), __METHOD__);
                $payload = null;
            }
        }

        if ($payload === null) {
            $payload = Yii::$app->request->bodyParams;
            $rawBody = $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        }

        $mapId = $payload['Id'] ?? null;

        try {
            Yii::$app->telegramChats->sendMessage($rawBody ?: '[rustmaps webhook] empty payload');
        } catch (\Throwable $throwable) {
            Yii::error(
                'RustMaps webhook failed to forward payload: ' . $throwable->getMessage(),
                __METHOD__
            );
        }

        if (empty($mapId)) {
            Yii::warning('RustMaps webhook skipped: empty Id field', __METHOD__);

            return [
                'success' => false,
                'error' => 'empty_id',
                'message' => 'Webhook payload did not include map Id',
            ];
        }

        $apiKey = Yii::$app->settings->get('maps_apiKey');
        if (empty($apiKey)) {
            Yii::warning('RustMaps webhook skipped: maps_apiKey is not configured', __METHOD__);

            return [
                'success' => false,
                'error' => 'missing_api_key',
                'message' => 'maps_apiKey setting is not configured',
            ];
        }

        $mapResponseRaw = null;
        try {
            $mapResponseRaw = (clone Yii::$app->curl)
                ->setHeader('X-API-Key', $apiKey)
                ->setHeader('accept', 'application/json')
                ->get('https://api.rustmaps.com/v4/maps/' . $mapId);

            Yii::$app->telegramChats->sendMessage('[rustmaps webhook] details: ' . $mapResponseRaw);
        } catch (\Throwable $throwable) {
            Yii::error(
                'RustMaps webhook failed to fetch map details: ' . $throwable->getMessage(),
                __METHOD__
            );

            return [
                'success' => false,
                'error' => 'request_failed',
                'message' => $throwable->getMessage(),
            ];
        }

        $mapResponse = json_decode($mapResponseRaw, true);
        if (!is_array($mapResponse) || ($mapResponse['meta']['statusCode'] ?? null) !== 200) {
            Yii::warning(
                'RustMaps webhook unexpected response: ' . $mapResponseRaw,
                __METHOD__
            );

            return [
                'success' => false,
                'error' => 'invalid_response',
                'message' => 'RustMaps API returned unexpected payload',
            ];
        }

        $mapData = $mapResponse['data'] ?? null;
        if (empty($mapData)) {
            Yii::warning('RustMaps webhook missing data block in response: ' . $mapResponseRaw, __METHOD__);

            return [
                'success' => false,
                'error' => 'empty_data',
                'message' => 'RustMaps API response missing data section',
            ];
        }

        $model = MapList::find()
            ->andWhere(['hash' => $mapData['id']])
            ->one();

        $isNewRecord = false;
        if ($model === null) {
            $model = new MapList();
            $model->hash = $mapData['id'];
            $model->created_at = date('Y-m-d H:i:s');
            $isNewRecord = true;
        }

        $model->url = $mapData['downloadUrl'] ?? ($payload['DownloadUrl'] ?? null);
        $model->size = (string)($mapData['size'] ?? $payload['Size'] ?? '');
        $model->map_type = $mapData['type'] ?? null;
        $model->seed = isset($mapData['seed']) ? (int)$mapData['seed'] : null;
        $model->save_version = isset($mapData['saveVersion']) ? (int)$mapData['saveVersion'] : null;
        $model->raw_image_url = $mapData['rawImageUrl'] ?? null;
        $model->image_url = $mapData['imageUrl'] ?? null;
        $model->image_icon_url = $mapData['imageIconUrl'] ?? null;
        $model->thumbnail_url = $mapData['thumbnailUrl'] ?? null;
        $model->is_staging = isset($mapData['isStaging']) ? (bool)$mapData['isStaging'] : null;
        $model->is_custom_map = isset($mapData['isCustomMap']) ? (bool)$mapData['isCustomMap'] : null;
        $model->can_download = isset($mapData['canDownload']) ? (bool)$mapData['canDownload'] : null;
        $model->total_monuments = isset($mapData['totalMonuments']) ? (int)$mapData['totalMonuments'] : null;
        $model->land_percentage = isset($mapData['landPercentageOfMap']) ? (int)$mapData['landPercentageOfMap'] : null;
        $model->islands = isset($mapData['islands']) ? (int)$mapData['islands'] : null;
        $model->mountains = isset($mapData['mountains']) ? (int)$mapData['mountains'] : null;
        $model->ice_lakes = isset($mapData['iceLakes']) ? (int)$mapData['iceLakes'] : null;
        $model->rivers = isset($mapData['rivers']) ? (int)$mapData['rivers'] : null;
        $model->lakes = isset($mapData['lakes']) ? (int)$mapData['lakes'] : null;
        $model->canyons = isset($mapData['canyons']) ? (int)$mapData['canyons'] : null;
        $model->oases = isset($mapData['oases']) ? (int)$mapData['oases'] : null;
        $model->buildable_rocks = isset($mapData['buildableRocks']) ? (int)$mapData['buildableRocks'] : null;
        $model->monuments_json = isset($mapData['monuments'])
            ? json_encode($mapData['monuments'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        $model->biome_percentages_json = isset($mapData['biomePercentages'])
            ? json_encode($mapData['biomePercentages'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
        $model->data_json = json_encode($mapData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $imageIconUrl = $mapData['imageIconUrl'] ?? null;
        if (!empty($imageIconUrl)) {
            try {
                $fileIconName = $mapData['id'] . '.jpg';
                $relativePreviewPath = '/uploads/maps/' . $mapData['id'] . '_200x200.jpg';
                $downloadedPath = Map::upload($imageIconUrl, $fileIconName);

                $fullPreviewPath = Yii::getAlias('@frontend/web') . $relativePreviewPath;
                DropImage::resizeImage($downloadedPath, $fullPreviewPath, 200);

                $model->image = '/uploads/maps/' . $fileIconName;
                $model->image_preview = $relativePreviewPath;
            } catch (\Throwable $throwable) {
                Yii::error(
                    'RustMaps webhook failed to process image: ' . $throwable->getMessage(),
                    __METHOD__
                );
            }
        }

        if (!$model->save()) {
            Yii::error('RustMaps webhook failed to save MapList: ' . json_encode($model->errors, JSON_UNESCAPED_UNICODE), __METHOD__);

            return [
                'success' => false,
                'error' => 'save_failed',
                'message' => 'Unable to persist map data',
                'details' => $model->errors,
            ];
        }

        return [
            'success' => true,
            'created' => $isNewRecord,
        ];
    }
}
