<?php

namespace common\components\queue\process;

use common\models\box\DropImage;
use common\models\map\Map;
use common\models\map\MapList;
use common\models\servers\Servers;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class MapGenerateJob extends BaseObject implements JobInterface
{
    public $serverId;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            /** @var Servers $server */
            $server = Servers::findOne($this->serverId);
            if (!$server) {
                Yii::error("MapGenerateJob: Server with ID {$this->serverId} not found", __METHOD__);
                return;
            }

            $sizes = [4250, 3750];


            // Подсчитываем количество подходящих размеров
            $countSizes = 0;
            foreach ($sizes as $size) {
                if ($server->min_map_size > $size) {
                    continue;
                }
                if ($server->max_map_size < $size) {
                    continue;
                }
                $countSizes++;
            }

            if ($countSizes === 0) {
                Yii::warning("MapGenerateJob: No suitable map sizes for server {$server->id}", __METHOD__);
                return;
            }

            // Генерируем карты для каждого подходящего размера
            foreach ($sizes as $size) {
                if ($server->min_map_size > $size) {
                    continue;
                }
                if ($server->max_map_size < $size) {
                    continue;
                }
                $countPerSize = (int)(10 / $countSizes);
                $this->generateMapsForSize($size, $server->id, $countPerSize);
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("MapGenerateJob: " . $e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage());
            Yii::error("MapGenerateJob error: " . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Генерация карт для указанного размера с использованием новой системы MapList
     * @param int $size
     * @param int $serverId
     * @param int $count
     * @throws \Exception
     */
    private function generateMapsForSize($size, $serverId, $count)
    {
        $apiKey = Yii::$app->settings->get('maps_apiKey');
        if (empty($apiKey)) {
            Yii::error("MapGenerateJob: maps_apiKey is not configured", __METHOD__);
            return;
        }

        // Получаем список карт через RustMaps API
        $mapList = Map::getMapsList($size);
        if (empty($mapList)) {
            Yii::warning("MapGenerateJob: No maps found for size {$size}", __METHOD__);
            return;
        }

        $processed = 0;
        foreach ($mapList as $item) {
            if ($processed >= $count) {
                break;
            }

            $mapId = $item['id'] ?? null;
            if (empty($mapId)) {
                continue;
            }

            // Проверяем, не существует ли уже карта с таким hash для этого сервера
            $existingMap = Map::find()
                ->andWhere(['is_archive' => 0])
                ->andWhere(['size' => $size])
                ->andWhere(['seed' => $item['seed'] ?? null])
                ->andWhere(['server_id' => $serverId])
                ->one();

            if ($existingMap) {
                continue;
            }

            // Получаем полную информацию о карте через API v4
            try {
                $mapResponseRaw = (clone Yii::$app->curl)
                    ->setHeader('X-API-Key', $apiKey)
                    ->setHeader('accept', 'application/json')
                    ->get('https://api.rustmaps.com/v4/maps/' . $mapId);

                $mapResponse = json_decode($mapResponseRaw, true);
                if (!is_array($mapResponse) || ($mapResponse['meta']['statusCode'] ?? null) !== 200) {
                    Yii::warning("MapGenerateJob: Invalid API response for map {$mapId}", __METHOD__);
                    continue;
                }

                $mapData = $mapResponse['data'] ?? null;
                if (empty($mapData)) {
                    continue;
                }

                // Сохраняем карту в MapList (новая система)
                $mapListModel = $this->saveMapToList($mapData);

                if ($mapListModel) {
                    // Создаем связь с сервером через старую модель Map (если нужно)
                    $this->createServerMapLink($mapListModel, $serverId, $size, $mapData);
                    $processed++;
                }

                sleep(1); // Задержка между запросами
            } catch (\Exception $e) {
                Yii::error("MapGenerateJob: Error processing map {$mapId}: " . $e->getMessage(), __METHOD__);
                continue;
            }
        }
    }

    /**
     * Сохранение карты в MapList (новая система)
     * @param array $mapData
     * @return MapList|null
     */
    private function saveMapToList($mapData)
    {
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

        // Заполняем данные карты
        $model->url = $mapData['downloadUrl'] ?? null;
        $sizeValue = $mapData['size'] ?? null;
        $model->size = $sizeValue !== null ? (string)$sizeValue : null;
        $model->size_int = $sizeValue !== null ? (int)$sizeValue : null;
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

        // Обрабатываем изображение
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
                    'MapGenerateJob failed to process image: ' . $throwable->getMessage(),
                    __METHOD__
                );
            }
        }

        if (!$model->save()) {
            Yii::error('MapGenerateJob failed to save MapList: ' . json_encode($model->errors, JSON_UNESCAPED_UNICODE), __METHOD__);
            return null;
        }

        return $model;
    }

    /**
     * Создание связи карты с сервером через старую модель Map (для обратной совместимости)
     * @param MapList $mapListModel
     * @param int $serverId
     * @param int $size
     * @param array $mapData
     */
    private function createServerMapLink($mapListModel, $serverId, $size, $mapData)
    {
        // Проверяем, не существует ли уже связь
        $existingMap = Map::find()
            ->andWhere(['is_archive' => 0])
            ->andWhere(['size' => $size])
            ->andWhere(['seed' => $mapListModel->seed])
            ->andWhere(['server_id' => $serverId])
            ->one();

        if ($existingMap) {
            return;
        }

        // Создаем новую связь
        $map = new Map();
        $map->mapId = $mapData['id'] ?? $mapListModel->hash;
        $map->name = ($mapListModel->size_int ?? $size) . "_" . ($mapListModel->seed ?? '');
        $map->is_staging = $mapListModel->is_staging ?? Yii::$app->settings->get('maps_staging');
        $map->link = $mapListModel->url;
        $map->image_link = $mapListModel->image_preview;
        $map->image_link_icons = $mapListModel->image;
        $map->seed = $mapListModel->seed;
        $map->size = $mapListModel->size_int ?? $size;
        $map->version = $mapListModel->save_version;
        $map->server_id = $serverId;
        $map->created_at = date('Y-m-d H:i:s');
        
        // Связываем с MapList, если есть поле map_list_id
        if ($map->hasAttribute('map_list_id')) {
            $map->map_list_id = $mapListModel->id;
        }

        if (!$map->save()) {
            Yii::error('MapGenerateJob failed to save Map: ' . json_encode($map->errors, JSON_UNESCAPED_UNICODE), __METHOD__);
        }
    }
}