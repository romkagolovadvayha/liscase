<?php

namespace common\models\tournament;

use common\components\base\ActiveRecord;

class CashRaceKeyToken extends ActiveRecord
{
    public const STATE_HELD = 'held';
    public const STATE_LOST = 'lost';
    public const STATE_DEPOSITED = 'deposited';
    public static function tableName() { return 'cash_race_key_token'; }
    public function rules()
    {
        return [
            [['token_uuid', 'tournament_id', 'server_id', 'user_id', 'steam_id', 'issued_at'], 'required'],
            [['tournament_id', 'server_id', 'user_id', 'terminal_session_id'], 'integer'],
            [['token_uuid'], 'string', 'max' => 36],
            [['steam_id'], 'string', 'max' => 32],
            [['state'], 'in', 'range' => [self::STATE_HELD, self::STATE_LOST, self::STATE_DEPOSITED]],
            [['issued_at', 'consumed_at'], 'safe'],
        ];
    }
}
