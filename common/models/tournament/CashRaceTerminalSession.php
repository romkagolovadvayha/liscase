<?php

namespace common\models\tournament;

use common\components\base\ActiveRecord;

class CashRaceTerminalSession extends ActiveRecord
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_DESTROYED = 'destroyed';
    public static function tableName() { return 'cash_race_terminal_session'; }
    public function rules()
    {
        return [
            [['tournament_id', 'server_id', 'session_uuid', 'monument_key', 'monument_name', 'spawned_at', 'expires_at'], 'required'],
            [['tournament_id', 'server_id'], 'integer'],
            [['session_uuid'], 'string', 'max' => 36],
            [['monument_key'], 'string', 'max' => 128],
            [['monument_name', 'position_json'], 'string', 'max' => 255],
            [['spawned_at', 'expires_at', 'closed_at'], 'safe'],
            [['status'], 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_EXPIRED, self::STATUS_DESTROYED]],
        ];
    }
}
