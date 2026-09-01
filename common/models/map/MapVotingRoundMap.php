<?php

namespace common\models\map;

/**
 * Candidate map included in one voting round.
 *
 * @property int $round_id
 * @property int $map_list_id
 * @property int $position
 * @property string $created_at
 */
class MapVotingRoundMap extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return 'map_voting_round_map';
    }

    public function rules()
    {
        return [
            [['round_id', 'map_list_id'], 'required'],
            [['round_id', 'map_list_id', 'position'], 'integer'],
            [['created_at'], 'safe'],
            [['round_id', 'map_list_id'], 'unique', 'targetAttribute' => ['round_id', 'map_list_id']],
        ];
    }

    public function getRound()
    {
        return $this->hasOne(MapVotingRound::class, ['id' => 'round_id']);
    }

    public function getMapList()
    {
        return $this->hasOne(MapList::class, ['id' => 'map_list_id']);
    }
}
