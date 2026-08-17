<?php

namespace backend\forms\tournament;

use common\models\medals\Medal;
use common\models\tournament\CashRaceTournament;
use common\models\tournament\Tournament;
use Yii;
use yii\helpers\ArrayHelper;

class CashRaceForm extends TournamentForm
{
    public $preview_only = 1;
    public $preview_steam_id = '76561198394504608';
    public $drop_chance = 0.12;
    public $drop_min = 1;
    public $drop_max = 2;
    public $key_shortname = 'keycard_green';
    public $key_skin_id = 0;
    public $terminal_active_seconds = 900;
    public $terminal_cooldown_min_seconds = 1200;
    public $terminal_cooldown_max_seconds = 2400;
    public $terminal_prefab = 'assets/prefabs/deployable/vendingmachine/vendingmachine.deployed.prefab';
    public $allowed_monuments_text = '';

    public function rules(): array
    {
        return ArrayHelper::merge(parent::rules(), [
            [['preview_only'], 'boolean'],
            [['preview_steam_id', 'key_shortname', 'terminal_prefab', 'allowed_monuments_text'], 'string'],
            [['drop_chance'], 'number', 'min' => 0, 'max' => 1],
            [['drop_min', 'drop_max', 'key_skin_id', 'terminal_active_seconds', 'terminal_cooldown_min_seconds', 'terminal_cooldown_max_seconds'], 'integer', 'min' => 0],
            [['drop_min'], 'compare', 'compareAttribute' => 'drop_max', 'operator' => '<='],
        ]);
    }

    public function afterFind(): void
    {
        parent::afterFind();
        $config = CashRaceTournament::findOne(['tournament_id' => $this->id]);
        if (!$config) return;
        foreach (['preview_only', 'preview_steam_id', 'drop_chance', 'drop_min', 'drop_max', 'key_shortname', 'key_skin_id', 'terminal_active_seconds', 'terminal_cooldown_min_seconds', 'terminal_cooldown_max_seconds', 'terminal_prefab'] as $field) {
            $this->$field = $config->$field;
        }
        $this->allowed_monuments_text = implode("\n", $config->getAllowedMonumentsArray());
    }

    public function saveWithUploads(): bool
    {
        $this->type = Tournament::TYPE_CASH_RACE;
        $this->max_clans = null;
        $this->max_participants_per_clan = null;
        $this->registration_ends_at = null;
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!parent::saveWithUploads()) {
                $transaction->rollBack();
                return false;
            }

            $config = CashRaceTournament::findOne(['tournament_id' => $this->id]) ?: new CashRaceTournament(['tournament_id' => $this->id]);
            foreach (['preview_only', 'preview_steam_id', 'drop_chance', 'drop_min', 'drop_max', 'key_shortname', 'key_skin_id', 'terminal_active_seconds', 'terminal_cooldown_min_seconds', 'terminal_cooldown_max_seconds', 'terminal_prefab'] as $field) {
                $config->$field = $this->$field;
            }
            $config->allowed_monuments = json_encode(array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string)$this->allowed_monuments_text)))), JSON_UNESCAPED_UNICODE);
            foreach (['gold', 'silver', 'bronze'] as $color) {
                $medal = Medal::findOne(['code' => 'cash-race-' . $color]);
                $field = $color . '_medal_id';
                $config->$field = $medal ? (int)$medal->id : null;
            }
            if (!$config->save()) {
                foreach ($config->getErrors() as $field => $errors) $this->addErrors([$field => $errors]);
                $transaction->rollBack();
                return false;
            }
            $transaction->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($transaction->isActive) $transaction->rollBack();
            throw $exception;
        }
    }
}
