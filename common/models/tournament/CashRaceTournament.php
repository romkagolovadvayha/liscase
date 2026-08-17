<?php

namespace common\models\tournament;

use common\components\base\ActiveRecord;
use common\models\medals\Medal;

class CashRaceTournament extends ActiveRecord
{
    public static function tableName() { return 'cash_race_tournament'; }

    public function rules()
    {
        return [
            [['tournament_id', 'drop_min', 'drop_max', 'terminal_active_seconds', 'terminal_cooldown_min_seconds', 'terminal_cooldown_max_seconds'], 'integer'],
            [['gold_medal_id', 'silver_medal_id', 'bronze_medal_id', 'key_skin_id'], 'integer'],
            [['preview_only'], 'boolean'],
            [['drop_chance'], 'number', 'min' => 0, 'max' => 1],
            [['preview_steam_id'], 'string', 'max' => 32],
            [['key_shortname'], 'string', 'max' => 64],
            [['terminal_prefab'], 'string', 'max' => 255],
            [['allowed_monuments', 'finished_at', 'awards_issued_at'], 'safe'],
            [['drop_min'], 'compare', 'compareAttribute' => 'drop_max', 'operator' => '<='],
        ];
    }

    public function getTournament() { return $this->hasOne(Tournament::class, ['id' => 'tournament_id']); }
    public function getGoldMedal() { return $this->hasOne(Medal::class, ['id' => 'gold_medal_id']); }
    public function getSilverMedal() { return $this->hasOne(Medal::class, ['id' => 'silver_medal_id']); }
    public function getBronzeMedal() { return $this->hasOne(Medal::class, ['id' => 'bronze_medal_id']); }

    public function getAllowedMonumentsArray(): array
    {
        if (is_array($this->allowed_monuments)) return array_values($this->allowed_monuments);
        $decoded = json_decode((string)$this->allowed_monuments, true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
