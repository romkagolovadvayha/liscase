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
}
