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
            $server->map_list_id = $winningMap->id;
            $server->save(false);
            
            // Загружаем карту на S3 хранилище
            if (!empty($winningMap->url) && !empty($server->tag)) {
                try {
                    // Скачиваем файл карты по URL
                    $mapFileContent = @file_get_contents($winningMap->url);
                    
                    if ($mapFileContent !== false && !empty($mapFileContent)) {
                        // Формируем путь в S3: server-maps/{server_tag}.map
                        $s3Path = 'server-maps/' . $server->tag . '.map';
                        
                        // Загружаем файл на S3 (если файл существует, он будет перезаписан)
                        Yii::$app->s3Api->uploadFile($s3Path, $mapFileContent);
                    }
                } catch (\Exception $e) {
                    // Логируем ошибку, но не прерываем выполнение метода
                    Yii::error('Error uploading map to S3: ' . $e->getMessage(), __METHOD__);
                }
            }
        }

        return $winningMap;
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
