<?php

namespace common\models\tournament;

use common\components\base\ActiveRecord;
use common\models\user\User;

class CashRaceScore extends ActiveRecord
{
    public static function tableName() { return 'cash_race_score'; }
    public function rules()
    {
        return [
            [['tournament_id', 'user_id', 'steam_id'], 'required'],
            [['tournament_id', 'user_id', 'keys_found', 'keys_lost', 'keys_deposited', 'position'], 'integer'],
            [['steam_id'], 'string', 'max' => 32],
            [['last_found_at', 'last_deposited_at'], 'safe'],
        ];
    }
    public function getUser() { return $this->hasOne(User::class, ['id' => 'user_id']); }
}
