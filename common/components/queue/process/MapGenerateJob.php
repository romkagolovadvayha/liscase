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
            // Всего добавляем 10 карт, распределяя их между размерами
            $totalCount = 10;
            foreach ($sizes as $size) {
                if ($server->min_map_size > $size) {
                    continue;
                }
                if ($server->max_map_size < $size) {
                    continue;
                }
                $countPerSize = (int)($totalCount / $countSizes);
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

        // Получаем список карт через RustMaps API (без кэша для свежих данных)
        $mapList = Map::getMapsList($size, false);
        if (empty($mapList)) {
            Yii::warning("MapGenerateJob: No maps found for size {$size}", __METHOD__);
            return;
        }

        $processed = 0;
        $skippedExisting = 0;
        $errors = 0;
        foreach ($mapList as $item) {
            if ($processed >= $count) {
                break;
            }

            // В ответе search API приходит mapId, а не id
            $mapId = $item['mapId'] ?? $item['id'] ?? null;
            if (empty($mapId)) {
                Yii::error("MapGenerateJob: Empty mapId in item: " . json_encode($item), __METHOD__);
                $errors++;
                continue;
            }

            // Проверяем, не существует ли уже карта с таким hash в MapList
            $existingMap = MapList::find()
                ->andWhere(['hash' => $mapId])
                ->one();

            if ($existingMap && !empty($existingMap->image) && !empty($existingMap->image_preview)) {
                $skippedExisting++;
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
                    Yii::error("MapGenerateJob: Invalid API response for map {$mapId}. Response: " . substr($mapResponseRaw, 0, 500), __METHOD__);
                    continue;
                }

                $mapData = $mapResponse['data'] ?? null;
                if (empty($mapData)) {
                    Yii::error("MapGenerateJob: Empty data in API response for map {$mapId}", __METHOD__);
                    continue;
                }

                // Проверяем наличие обязательного поля id
                if (empty($mapData['id'])) {
                    Yii::error("MapGenerateJob: Missing 'id' field in mapData for mapId {$mapId}. Data: " . json_encode($mapData), __METHOD__);
                    continue;
                }

                // Сохраняем карту в MapList
                $mapListModel = $this->saveMapToList($mapData);

                if ($mapListModel) {
                    $processed++;
                } else {
                    Yii::error("MapGenerateJob: Failed to save map {$mapData['id']} to MapList", __METHOD__);
                }

                sleep(1); // Задержка между запросами
            } catch (\Exception $e) {
                Yii::error("MapGenerateJob: Error processing map {$mapId}: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString(), __METHOD__);
                $errors++;
                continue;
            }
        }

        if ($errors > 0) {
            $summary = "MapGenerateJob: Completed for size {$size}. Processed: {$processed}, Skipped (existing): {$skippedExisting}, Errors: {$errors}";
            Yii::warning($summary, __METHOD__);
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
            $downloadedPath = null;
            $tempPreviewPath = null;
            try {
                $fileIconName = $mapData['id'] . '.jpg';
                $relativePreviewPath = '/uploads/maps/' . $mapData['id'] . '_200x200.jpg';
                
                // Скачиваем и обрабатываем изображение локально (с watermark)
                $downloadedPath = Map::upload($imageIconUrl, $fileIconName);
                
                // Создаем превью во временном файле
                $tempDir = sys_get_temp_dir();
                $tempPreviewPath = $tempDir . '/' . uniqid('map_preview_') . '.jpg';
                if (!DropImage::resizeImage($downloadedPath, $tempPreviewPath, 200)) {
                    throw new \RuntimeException('Failed to create map preview');
                }
                
                // Загружаем оригинал в S3
                $s3Api = Yii::$app->s3Api;
                $s3KeyOriginal = 'uploads/maps/' . $fileIconName;
                $originalContent = file_get_contents($downloadedPath);
                $s3ResultOriginal = $s3Api->putFile($s3KeyOriginal, $originalContent, 'image/jpeg');
                
                // Загружаем превью в S3
                $s3KeyPreview = 'uploads/maps/' . $mapData['id'] . '_200x200.jpg';
                $previewContent = file_exists($tempPreviewPath) ? file_get_contents($tempPreviewPath) : null;
                $s3ResultPreview = $previewContent ? $s3Api->putFile($s3KeyPreview, $previewContent, 'image/jpeg') : false;
                
                if ($s3ResultOriginal !== false) {
                    $model->image = '/uploads/maps/' . $fileIconName;
                }
                if ($s3ResultPreview !== false) {
                    $model->image_preview = $relativePreviewPath;
                }
            } catch (\Throwable $throwable) {
                Yii::error(
                    'MapGenerateJob failed to process image: ' . $throwable->getMessage(),
                    __METHOD__
                );
            } finally {
                if ($downloadedPath && is_file($downloadedPath)) {
                    @unlink($downloadedPath);
                }
                if ($tempPreviewPath && is_file($tempPreviewPath)) {
                    @unlink($tempPreviewPath);
                }
            }
        }

        if (!$model->save()) {
            Yii::error('MapGenerateJob failed to save MapList: ' . json_encode($model->errors, JSON_UNESCAPED_UNICODE), __METHOD__);
            return null;
        }

        return $model;
    }
}
