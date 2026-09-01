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
     * Возвращает публичный URL изображения из S3 (или как есть, если уже полный URL).
     * @param string|null $path Поле image или image_preview
     * @return string|null
     */
    protected function buildImageUrl($path)
    {
        if (empty($path)) {
            return null;
        }
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        if (!\Yii::$app->has('s3Api')) {
            return $path;
        }
        $s3Key = ltrim($path, '/');
        return \Yii::$app->s3Api->getPublicUrl($s3Key);
    }

    /**
     * Публичный URL основного изображения карты (S3).
     * @return string|null
     */
    public function getImageUrl()
    {
        return $this->buildImageUrl($this->image);
    }

    /**
     * Публичный URL превью изображения карты (S3).
     * @return string|null
     */
    public function getImagePreviewUrl()
    {
        return $this->buildImageUrl($this->image_preview ?? $this->image);
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

    public function getVotingRounds()
    {
        return $this->hasMany(MapVotingRound::class, ['id' => 'round_id'])
            ->viaTable(MapVotingRoundMap::tableName(), ['map_list_id' => 'id']);
    }

    public function isValidForServer(\common\models\servers\Servers $server): bool
    {
        if ($this->seed === null || $this->size_int === null || $this->save_version === null) {
            return false;
        }

        $seed = (int)$this->seed;
        $size = (int)$this->size_int;
        $mapType = strtolower(trim((string)$this->map_type));

        return $seed >= 0
            && $seed <= 2147483647
            && $size >= 1000
            && $size <= 6000
            && $size >= (int)$server->min_map_size
            && $size <= (int)$server->max_map_size
            && in_array($mapType, ['procedural map', 'procedural'], true)
            && !(bool)$this->is_custom_map;
    }

    /**
     * @param int $serverId
     * @return \yii\db\ActiveQuery
     */
    public function getVotesForServer(int $serverId)
    {
        $query = $this->getVotes()->andWhere(['server_id' => $serverId]);
        $round = MapVotingRound::getOpenForServer($serverId);
        return $round ? $query->andWhere(['round_id' => $round->id]) : $query->andWhere(['round_id' => 0]);
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
        $round = MapVotingRound::getOpenForServer($serverId);
        if (!$round || !$round->containsMap((int)$this->id)) {
            return false;
        }

        if ($this->hasUserVoted($serverId, $userId)) {
            return true;
        }

        $vote = new MapListVote([
            'map_list_id' => $this->id,
            'server_id' => $serverId,
            'round_id' => $round->id,
            'user_id' => $userId,
        ]);

        return $vote->save();
    }

    public function removeVote(int $serverId, int $userId): bool
    {
        $round = MapVotingRound::getOpenForServer($serverId);
        if (!$round) {
            return false;
        }

        $vote = MapListVote::find()
            ->andWhere([
                'map_list_id' => $this->id,
                'server_id' => $serverId,
                'round_id' => $round->id,
                'user_id' => $userId,
            ])
            ->one();

        if (!$vote) {
            return true;
        }

        return (bool)$vote->delete();
    }

    /**
     * Определяет выигрышную карту для сервера на основе голосов (без фиксации)
     * 
     * @param int $serverId ID сервера
     * @return self|null Выигрышная карта или null
     */
    public static function getWinningMapForServer(int $serverId): ?self
    {
        $round = MapVotingRound::getOpenForServer($serverId);
        if (!$round) {
            return null;
        }

        $result = $round->result();
        return $result['status'] === 'winner' ? $result['map'] : null;
    }

    /**
     * Выигрышная карта по голосам на сервере среди карт, которые ещё не зафиксированы ни на одном сервере.
     * Для формы фиксации: не подставлять карту, которая уже назначена любому серверу.
     *
     * @param int $serverId ID сервера
     * @return self|null Карта или null
     */
    public static function getWinningMapForServerUnfixedOnly(int $serverId): ?self
    {
        // Procedural seeds may safely be used on more than one server. Silently
        // replacing the voted winner because another server uses it violates
        // the vote, so cross-server fixation is intentionally not a filter.
        return self::getWinningMapForServer($serverId);
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
        $round = MapVotingRound::getOpenForServer($serverId);
        if (!$round) {
            return null;
        }

        $result = $round->result();
        if ($result['status'] !== 'winner' || !$result['map']) {
            // Never erase the currently assigned map merely because a new vote
            // has no votes or is tied.
            return null;
        }

        return $round->fixMap($result['map']) ? $result['map'] : null;
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
                    continue;
                }
                
                if ($httpCode >= 200 && $httpCode < 300) {
                    if (!empty($content)) {
                        return $content;
                    } else {
                        Yii::warning("Attempt " . ($attemptNumber + 1) . " downloaded empty file from URL: {$url}, map ID: {$mapId}, HTTP code: {$httpCode}", __METHOD__);
                    }
                } else {
                    Yii::warning("Attempt " . ($attemptNumber + 1) . " failed with HTTP code {$httpCode} from URL: {$url}, map ID: {$mapId}", __METHOD__);
                    
                    // Для 5xx ошибок делаем повторные попытки, для 4xx - сразу возвращаем false
                    if ($httpCode >= 400 && $httpCode < 500) {
                        return false;
                    }
                }
            } catch (\Exception $e) {
                if ($ch !== null) {
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
