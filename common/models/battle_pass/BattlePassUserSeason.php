<?php

namespace common\models\battle_pass;

use common\components\base\ActiveRecord;

/**
 * @property int $id
 * @property int $season_id
 * @property int $user_id
 * @property string $completed_at
 * @property string|null $reward_given_at
 */
class BattlePassUserSeason extends ActiveRecord
{
    public static function tableName()
    {
        return 'battle_pass_user_season';
    }

    public function rules()
    {
        return [
            [['season_id', 'user_id', 'completed_at'], 'required'],
            [['season_id', 'user_id'], 'integer'],
            [['completed_at', 'reward_given_at'], 'safe'],
        ];
    }
}
