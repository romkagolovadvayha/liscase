<?php

namespace common\models\tournament;

use common\components\base\ActiveRecord;

class CashRaceDeposit extends ActiveRecord
{
    public static function tableName() { return 'cash_race_deposit'; }
    public function rules()
    {
        return [
            [['deposit_uuid', 'tournament_id', 'terminal_session_id', 'server_id', 'user_id', 'steam_id', 'keys_count'], 'required'],
            [['tournament_id', 'terminal_session_id', 'server_id', 'user_id', 'keys_count'], 'integer'],
            [['deposit_uuid'], 'string', 'max' => 36],
            [['steam_id'], 'string', 'max' => 32],
        ];
    }
}
