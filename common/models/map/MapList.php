<?php

namespace common\models\map;

use Yii;

/**
 * This is the model class for table "map_list".
 *
 * @property int $id
 * @property string|null $hash
 * @property string|null $url
 * @property string|null $image
 * @property string|null $image_preview
 * @property string|null $size
 * @property int|null $size_int
 * @property string|null $map_type
 * @property int|null $seed
 * @property int|null $save_version
 * @property string|null $raw_image_url
 * @property string|null $image_url
 * @property string|null $image_icon_url
 * @property string|null $thumbnail_url
 * @property bool|null $is_staging
 * @property bool|null $is_custom_map
 * @property bool|null $can_download
 * @property int|null $total_monuments
 * @property string|null $monuments_json
 * @property int|null $land_percentage
 * @property string|null $biome_percentages_json
 * @property int|null $islands
 * @property int|null $mountains
 * @property int|null $ice_lakes
 * @property int|null $rivers
 * @property int|null $lakes
 * @property int|null $canyons
 * @property int|null $oases
 * @property int|null $buildable_rocks
 * @property string|null $data_json
 * @property string|null $created_at
 *
 * @property Map[] $maps
 */
class MapList extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'map_list';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['created_at'], 'safe'],
            [['size_int', 'seed', 'save_version', 'total_monuments', 'land_percentage', 'islands', 'mountains', 'ice_lakes', 'rivers', 'lakes', 'canyons', 'oases', 'buildable_rocks'], 'integer'],
            [['is_staging', 'is_custom_map', 'can_download'], 'boolean'],
            [['monuments_json', 'biome_percentages_json', 'data_json'], 'string'],
            [['hash', 'url', 'image', 'image_preview', 'size', 'raw_image_url', 'image_url', 'image_icon_url', 'thumbnail_url'], 'string', 'max' => 255],
            [['map_type'], 'string', 'max' => 50],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'hash' => 'Hash',
            'url' => 'Url',
            'image' => 'Image',
            'size' => 'Size',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[Maps]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMaps()
    {
        return $this->hasMany(Map::class, ['map_list_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getVotes()
    {
        return $this->hasMany(MapListVote::class, ['map_list_id' => 'id']);
    }

    /**
     * @param int $serverId
     * @return \yii\db\ActiveQuery
     */
    public function getVotesForServer(int $serverId)
    {
        return $this->getVotes()->andWhere(['server_id' => $serverId]);
    }

    public function getVoteCount(int $serverId): int
    {
        return (int)$this->getVotesForServer($serverId)->count();
    }

    public function hasUserVoted(int $serverId, int $userId): bool
    {
        return $this->getVotesForServer($serverId)
            ->andWhere(['user_id' => $userId])
            ->exists();
    }

    public function addVote(int $serverId, int $userId): bool
    {
        if ($this->hasUserVoted($serverId, $userId)) {
            return true;
        }

        $vote = new MapListVote([
            'map_list_id' => $this->id,
            'server_id' => $serverId,
            'user_id' => $userId,
        ]);

        return $vote->save();
    }

    public function removeVote(int $serverId, int $userId): bool
    {
        $vote = MapListVote::find()
            ->andWhere([
                'map_list_id' => $this->id,
                'server_id' => $serverId,
                'user_id' => $userId,
            ])
            ->one();

        if (!$vote) {
            return true;
        }

        return (bool)$vote->delete();
    }

    /**
     * Фиксирует карту с наибольшим количеством голосов для указанного сервера
     * и обновляет поле map_list_id в таблице servers
     * 
     * @param int $serverId ID сервера
     * @return MapList|null Карта с наибольшим количеством голосов или null, если голосов нет
     */
    public static function fixWinningMapForServer(int $serverId): ?self
    {
        // Получаем количество голосов для каждой карты на данном сервере
        $voteCounts = MapListVote::find()
            ->select(['map_list_id', 'vote_count' => 'COUNT(*)'])
            ->where(['server_id' => $serverId])
            ->groupBy('map_list_id')
            ->orderBy(['vote_count' => SORT_DESC])
            ->asArray()
            ->all();

        if (empty($voteCounts)) {
            // Нет голосов, можно сбросить map_list_id или оставить null
            $server = \common\models\servers\Servers::findOne($serverId);
            if ($server) {
                $server->map_list_id = null;
                $server->save(false);
            }
            return null;
        }

        // Находим максимальное количество голосов
        $maxVotes = (int)$voteCounts[0]['vote_count'];
        
        // Получаем все карты с максимальным количеством голосов
        $winningMapIds = [];
        foreach ($voteCounts as $row) {
            if ((int)$row['vote_count'] === $maxVotes) {
                $winningMapIds[] = (int)$row['map_list_id'];
            } else {
                break; // Так как сортировка по убыванию, дальше будут карты с меньшим количеством голосов
            }
        }

        // Если несколько карт с одинаковым количеством голосов, выбираем самую новую по created_at
        $winningMap = self::find()
            ->where(['id' => $winningMapIds])
            ->orderBy(['created_at' => SORT_DESC])
            ->one();

        if (!$winningMap) {
            return null;
        }

        // Обновляем map_list_id в таблице servers
        $server = \common\models\servers\Servers::findOne($serverId);
        if ($server) {
            // Сохраняем старый map_list_id для удаления старого файла
            $oldMapListId = $server->map_list_id;
            $oldMap = null;
            $oldS3Path = null;
            
            // Если есть старая зафиксированная карта, получаем путь к её файлу для удаления
            if (!empty($oldMapListId)) {
                $oldMap = self::findOne($oldMapListId);
                if ($oldMap && !empty($oldMap->url)) {
                    $oldFileName = basename(parse_url($oldMap->url, PHP_URL_PATH));
                    if (!empty($oldFileName)) {
                        $oldS3Path = 'server-maps/' . $oldFileName;
                    }
                }
            }
            
            $server->map_list_id = $winningMap->id;
            $server->save(false);
            
            // Удаляем старый файл из S3 перед загрузкой нового
            if (!empty($oldS3Path)) {
                try {
                    $deleted = Yii::$app->s3Api->deleteFile($oldS3Path);
                    if ($deleted) {
                        Yii::info("Successfully deleted old map file from S3: {$oldS3Path}, old map ID: {$oldMapListId}, server ID: {$serverId}", __METHOD__);
                    } else {
                        Yii::warning("Failed to delete old map file from S3: {$oldS3Path}, old map ID: {$oldMapListId}, server ID: {$serverId}", __METHOD__);
                    }
                } catch (\Exception $e) {
                    Yii::error("Error deleting old map file from S3: {$oldS3Path}, old map ID: {$oldMapListId}, server ID: {$serverId}, error: " . $e->getMessage(), __METHOD__);
                }
            }
            
            // Загружаем карту на S3 хранилище
            if (empty($winningMap->url)) {
                Yii::error("Map URL is empty for map ID: {$winningMap->id}, server ID: {$serverId}", __METHOD__);
            } elseif (empty($server->tag)) {
                Yii::error("Server tag is empty for server ID: {$serverId}, map ID: {$winningMap->id}", __METHOD__);
            } else {
                try {
                    // Пытаемся скачать файл карты с повторными попытками
                    $mapFileContent = self::downloadMapFileWithRetry($winningMap->url, $winningMap->id);
                    
                    if ($mapFileContent === false) {
                        Yii::error("Failed to download map file after all retries from URL: {$winningMap->url}, map ID: {$winningMap->id}", __METHOD__);
                    } elseif (empty($mapFileContent)) {
                        Yii::error("Downloaded map file is empty from URL: {$winningMap->url}, map ID: {$winningMap->id}", __METHOD__);
                    } else {
                        // Извлекаем оригинальное имя файла из URL
                        $originalFileName = basename(parse_url($winningMap->url, PHP_URL_PATH));
                        
                        // Если не удалось извлечь имя файла, используем fallback
                        if (empty($originalFileName)) {
                            $originalFileName = $server->tag . '.map';
                        }
                        
                        // Формируем путь в S3: server-maps/{оригинальное_имя_файла}
                        $s3Path = 'server-maps/' . $originalFileName;
                        
                        // Загружаем файл на S3 (если файл существует, он будет перезаписан)
                        $fileSize = strlen($mapFileContent);
                        $result = Yii::$app->s3Api->uploadFile($s3Path, $mapFileContent);
                        
                        if ($result) {
                            Yii::error("Successfully uploaded map to S3: {$s3Path}, size: {$fileSize} bytes, map ID: {$winningMap->id}, server ID: {$serverId}", __METHOD__);
                        } else {
                            Yii::error("Failed to upload map to S3: {$s3Path}, map ID: {$winningMap->id}, server ID: {$serverId}", __METHOD__);
                        }
                    }
                } catch (\Exception $e) {
                    // Логируем ошибку, но не прерываем выполнение метода
                    Yii::error('Error uploading map to S3: ' . $e->getMessage() . ', URL: ' . $winningMap->url . ', map ID: ' . $winningMap->id . ', server ID: ' . $serverId, __METHOD__);
                }
            }
        }

        return $winningMap;
    }

    /**
     * Скачивает файл карты по URL с повторными попытками при ошибках
     * 
     * @param string $url URL файла карты
     * @param int $mapId ID карты для логирования
     * @param int $maxAttempts Максимальное количество попыток
     * @return string|false Содержимое файла или false в случае ошибки
     */
    private static function downloadMapFileWithRetry(string $url, int $mapId, int $maxAttempts = 3)
    {
        $attempts = [0, 3, 5]; // Задержки между попытками в секундах
        $attempts = array_slice($attempts, 0, $maxAttempts);
        
        foreach ($attempts as $attemptNumber => $sleep) {
            if ($sleep > 0 && $attemptNumber > 0) {
                sleep($sleep);
            }
            
            $ch = null;
            try {
                // Используем curl для лучшего контроля над таймаутами и обработкой ошибок
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 минут таймаут для больших файлов
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // 30 секунд на подключение
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                
                $content = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                
                if ($content === false || !empty($error)) {
                    Yii::warning("Attempt " . ($attemptNumber + 1) . " failed to download map file from URL: {$url}, map ID: {$mapId}, error: {$error}", __METHOD__);
                    curl_close($ch);
                    continue;
                }
                
                if ($httpCode >= 200 && $httpCode < 300) {
                    if (!empty($content)) {
                        curl_close($ch);
                        return $content;
                    } else {
                        Yii::warning("Attempt " . ($attemptNumber + 1) . " downloaded empty file from URL: {$url}, map ID: {$mapId}, HTTP code: {$httpCode}", __METHOD__);
                        curl_close($ch);
                    }
                } else {
                    Yii::warning("Attempt " . ($attemptNumber + 1) . " failed with HTTP code {$httpCode} from URL: {$url}, map ID: {$mapId}", __METHOD__);
                    curl_close($ch);
                    
                    // Для 5xx ошибок делаем повторные попытки, для 4xx - сразу возвращаем false
                    if ($httpCode >= 400 && $httpCode < 500) {
                        return false;
                    }
                }
            } catch (\Exception $e) {
                if ($ch !== null) {
                    curl_close($ch);
                }
                Yii::warning("Attempt " . ($attemptNumber + 1) . " exception when downloading map file from URL: {$url}, map ID: {$mapId}, error: " . $e->getMessage(), __METHOD__);
            }
        }
        
        return false;
    }

    /**
     * Удаляет все не зафиксированные карты из таблицы map_list
     * Остаются только карты, которые зафиксированы хотя бы на одном сервере
     * 
     * @return void
     */
    public static function deleteUnfixedMaps(): void
    {
        try {
            // Получаем ID всех зафиксированных карт на любом из серверов
            $fixedMapIds = \common\models\servers\Servers::find()
                ->select('map_list_id')
                ->andWhere(['IS NOT', 'map_list_id', null])
                ->column();
            
            // Если есть зафиксированные карты, удаляем все остальные
            if (!empty($fixedMapIds)) {
                self::deleteAll(['NOT IN', 'id', $fixedMapIds]);
            } else {
                // Если нет зафиксированных карт, удаляем все карты
                self::deleteAll();
            }
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем выполнение метода
            Yii::error('Error deleting unfixed maps: ' . $e->getMessage(), __METHOD__);
        }
    }
}
