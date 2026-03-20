<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use common\models\servers\Servers;
use common\models\user\UserTop;
use Yii;
use yii\base\InvalidCallException;
use yii\base\UnknownPropertyException;

/**
 * Заголовок статистики клана за вайп; числовые метрики — в [[ClanStatisticsValue]] (stat_key / value).
 *
 * @property int $id
 * @property int $clan_id
 * @property int $server_id
 * @property string|null $wipe
 * @property int $updated_at
 * @property string|null $last_activity_date
 *
 * @property Clan $clan
 * @property Servers $server
 * @property ClanStatisticsValue[] $statValues
 */
class ClanStatistics extends ActiveRecord
{
    /** @var array<string, float> кэш key => value для __get и пересчёта */
    private $_statsMap = [];

    public static function tableName(): string
    {
        return 'clan_statistics';
    }

    public function rules(): array
    {
        return [
            [['clan_id', 'server_id'], 'required'],
            [['clan_id', 'server_id', 'updated_at'], 'integer'],
            [['wipe'], 'string', 'max' => 255],
            [['last_activity_date'], 'safe'],
            [['clan_id', 'server_id', 'wipe'], 'unique', 'targetAttribute' => ['clan_id', 'server_id', 'wipe']],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'clan_id' => Yii::t('common', 'Клан'),
            'server_id' => Yii::t('common', 'Сервер'),
            'wipe' => Yii::t('common', 'Вайп'),
            'updated_at' => Yii::t('common', 'Дата обновления'),
        ];
    }

    public function getClan(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Clan::class, ['id' => 'clan_id']);
    }

    public function getServer(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
    }

    public function getStatValues(): \yii\db\ActiveQuery
    {
        return $this->hasMany(ClanStatisticsValue::class, ['clan_statistics_id' => 'id']);
    }

    public function init(): void
    {
        parent::init();
        $this->_statsMap = [];
    }

    public function afterFind(): void
    {
        parent::afterFind();
        if ($this->isRelationPopulated('statValues')) {
            $this->_statsMap = [];
            foreach ($this->statValues as $sv) {
                $this->_statsMap[(string)$sv->stat_key] = (float)$sv->value;
            }
        } else {
            $this->loadStatsMapFromDatabase();
        }
    }

    private function loadStatsMapFromDatabase(): void
    {
        $this->_statsMap = [];
        if (!$this->id) {
            return;
        }
        $rows = ClanStatisticsValue::find()
            ->select(['stat_key', 'value'])
            ->where(['clan_statistics_id' => $this->id])
            ->asArray()
            ->all();
        foreach ($rows as $row) {
            $this->_statsMap[(string)$row['stat_key']] = (float)$row['value'];
        }
    }

    /**
     * Плоский массив для API / JSON: заголовок + все stat_key.
     *
     * @return array<string, mixed>
     */
    public function getStatisticsForApi(): array
    {
        $base = $this->getAttributes();
        foreach ($this->_statsMap as $k => $v) {
            $base[$k] = $v;
        }

        return $base;
    }

    /**
     * @internal для тестов
     * @return array<string, float>
     */
    public function getStatsMap(): array
    {
        return $this->_statsMap;
    }

    public function getStatValue(string $key): float
    {
        return (float)($this->_statsMap[$key] ?? 0);
    }

    private function setStatValue(string $key, $value): void
    {
        $this->_statsMap[$key] = (float)$value;
    }

    private function addToStat(string $key, $delta): void
    {
        $this->_statsMap[$key] = $this->getStatValue($key) + (float)$delta;
    }

    private function isStatMagicKey(string $name): bool
    {
        if (strpos($name, 'total_') === 0 || strpos($name, 'top_') === 0) {
            return true;
        }

        return in_array($name, [
            'raids_completed', 'raids_defended', 'wars_won', 'wars_lost', 'total_activity_days',
        ], true);
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($this->updated_at === null || $this->updated_at === '') {
            $this->updated_at = time();
        }

        return true;
    }

    public function __get($name)
    {
        try {
            return parent::__get($name);
        } catch (UnknownPropertyException $e) {
            if ($this->isStatMagicKey($name)) {
                return $this->getStatValue($name);
            }
            throw $e;
        }
    }

    public function __isset($name)
    {
        if (parent::__isset($name)) {
            return true;
        }

        return $this->isStatMagicKey($name);
    }

    public function __set($name, $value)
    {
        if ($this->hasAttribute($name)) {
            parent::__set($name, $value);

            return;
        }
        if ($this->isStatMagicKey($name)) {
            $this->setStatValue($name, $value);

            return;
        }
        parent::__set($name, $value);
    }

    public function canSetProperty($name, $checkVars = true, $checkBehaviors = true)
    {
        if ($this->isStatMagicKey($name)) {
            return true;
        }

        return parent::canSetProperty($name, $checkVars, $checkBehaviors);
    }

    /**
     * {@inheritdoc}
     * Сохраняет заголовок и строки clan_statistics_values.
     */
    public function save($runValidation = true, $attributeNames = null)
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!parent::save($runValidation, $attributeNames)) {
                $transaction->rollBack();

                return false;
            }
            $this->persistStatsMap();
            $transaction->commit();

            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    private function persistStatsMap(): void
    {
        if (!$this->id) {
            throw new InvalidCallException('ClanStatistics must be saved before persisting stat values.');
        }
        ClanStatisticsValue::deleteAll(['clan_statistics_id' => $this->id]);
        if ($this->_statsMap === []) {
            return;
        }
        $batch = [];
        foreach ($this->_statsMap as $key => $value) {
            $batch[] = [(int)$this->id, (string)$key, (float)$value];
        }
        if ($batch === []) {
            return;
        }
        Yii::$app->db->createCommand()->batchInsert(
            ClanStatisticsValue::tableName(),
            ['clan_statistics_id', 'stat_key', 'value'],
            $batch
        )->execute();
    }

    /**
     * Обновление статистики клана
     */
    public function updateStatistics(): bool
    {
        $memberStatistics = ClanMemberStatistics::find()
            ->where(['clan_id' => $this->clan_id, 'server_id' => $this->server_id, 'wipe' => $this->wipe])
            ->all();

        $this->resetStatistics();

        foreach ($memberStatistics as $memberStat) {
            $this->aggregateMemberStatistics($memberStat);
        }

        $this->calculateTopRatings();
        $this->updated_at = time();

        return $this->save(false);
    }

    protected function resetStatistics(): void
    {
        $this->_statsMap = [];
        foreach (self::aggregatableTotalKeys() as $k) {
            $this->_statsMap[$k] = 0.0;
        }
        foreach (self::extraTotalKeys() as $k) {
            $this->_statsMap[$k] = 0.0;
        }
    }

    /**
     * Ключи total_*, суммируемые из участников.
     *
     * @return string[]
     */
    private static function aggregatableTotalKeys(): array
    {
        return [
            'total_kills', 'total_deaths', 'total_scientists', 'total_wounded', 'total_tcs_destroyed', 'total_nude_kills',
            'total_hits_head', 'total_hits_neck', 'total_hits_chest', 'total_hits_lowerspine',
            'total_hits_lefthand', 'total_hits_leftleg', 'total_hits_leftfoot',
            'total_hits_righthand', 'total_hits_rightleg', 'total_hits_rightfoot',
            'total_c4thrown', 'total_satchelsthrown', 'total_rocket_basic', 'total_rocket_hv', 'total_rocket_fire',
            'total_ammo_explosive', 'total_grenade_f1_deployed', 'total_grenade_molotov_deployed', 'total_grenade_beancan_deployed',
            'total_wood', 'total_stones', 'total_metal_ore', 'total_sulfur_ore',
            'total_f_fish_anchovy', 'total_f_fish_catfish', 'total_f_fish_herring', 'total_f_fish_orangeroughy',
            'total_f_fish_salmon', 'total_f_fish_sardine', 'total_f_fish_smallshark', 'total_f_fish_troutsmall', 'total_f_fish_yellowperch',
            'total_chicken', 'total_bear', 'total_boar', 'total_polarbear', 'total_stag', 'total_horse',
            'total_wolf2', 'total_wolf', 'total_simpleshark', 'total_panther', 'total_crocodile', 'total_tiger',
            'total_gathered_cloth', 'total_gathered_pumpkin', 'total_gathered_corn', 'total_gathered_green_berry',
            'total_gathered_blue_berry', 'total_gathered_yellow_berry', 'total_gathered_red_berry', 'total_gathered_white_berry',
            'total_gathered_black_berry', 'total_gathered_potato', 'total_gathered_orchid', 'total_gathered_rose',
            'total_gathered_sunflower', 'total_gathered_wheat',
            'total_playtime', 'total_crate_open', 'total_barrel', 'total_helicopters', 'total_bradleys',
            'total_research_table_looted', 'total_excavator_mined',
        ];
    }

    /**
     * Прочие total_* из старой схемы (не агрегируются из участников в текущем коде).
     *
     * @return string[]
     */
    private static function extraTotalKeys(): array
    {
        return ['raids_completed', 'raids_defended', 'wars_won', 'wars_lost', 'total_activity_days'];
    }

    protected function aggregateMemberStatistics(ClanMemberStatistics $memberStat): void
    {
        $this->addToStat('total_kills', $memberStat->kills);
        $this->addToStat('total_deaths', $memberStat->deaths);
        $this->addToStat('total_scientists', $memberStat->scientists);
        $this->addToStat('total_wounded', $memberStat->wounded);
        $this->addToStat('total_tcs_destroyed', $memberStat->tcs_destroyed);
        $this->addToStat('total_nude_kills', $memberStat->nude_kills);

        $this->addToStat('total_hits_head', $memberStat->hits_head);
        $this->addToStat('total_hits_neck', $memberStat->hits_neck);
        $this->addToStat('total_hits_chest', $memberStat->hits_chest);
        $this->addToStat('total_hits_lowerspine', $memberStat->hits_lowerspine);
        $this->addToStat('total_hits_lefthand', $memberStat->hits_lefthand);
        $this->addToStat('total_hits_leftleg', $memberStat->hits_leftleg);
        $this->addToStat('total_hits_leftfoot', $memberStat->hits_leftfoot);
        $this->addToStat('total_hits_righthand', $memberStat->hits_righthand);
        $this->addToStat('total_hits_rightleg', $memberStat->hits_rightleg);
        $this->addToStat('total_hits_rightfoot', $memberStat->hits_rightfoot);

        $this->addToStat('total_c4thrown', $memberStat->c4thrown);
        $this->addToStat('total_satchelsthrown', $memberStat->satchelsthrown);
        $this->addToStat('total_rocket_basic', $memberStat->rocket_basic);
        $this->addToStat('total_rocket_hv', $memberStat->rocket_hv);
        $this->addToStat('total_rocket_fire', $memberStat->rocket_fire);
        $this->addToStat('total_ammo_explosive', $memberStat->ammo_explosive);
        $this->addToStat('total_grenade_f1_deployed', $memberStat->grenade_f1_deployed);
        $this->addToStat('total_grenade_molotov_deployed', $memberStat->grenade_molotov_deployed);
        $this->addToStat('total_grenade_beancan_deployed', $memberStat->grenade_beancan_deployed);

        $this->addToStat('total_wood', $memberStat->wood);
        $this->addToStat('total_stones', $memberStat->stones);
        $this->addToStat('total_metal_ore', $memberStat->metal_ore);
        $this->addToStat('total_sulfur_ore', $memberStat->sulfur_ore);

        $this->addToStat('total_f_fish_anchovy', $memberStat->f_fish_anchovy);
        $this->addToStat('total_f_fish_catfish', $memberStat->f_fish_catfish);
        $this->addToStat('total_f_fish_herring', $memberStat->f_fish_herring);
        $this->addToStat('total_f_fish_orangeroughy', $memberStat->f_fish_orangeroughy);
        $this->addToStat('total_f_fish_salmon', $memberStat->f_fish_salmon);
        $this->addToStat('total_f_fish_sardine', $memberStat->f_fish_sardine);
        $this->addToStat('total_f_fish_smallshark', $memberStat->f_fish_smallshark);
        $this->addToStat('total_f_fish_troutsmall', $memberStat->f_fish_troutsmall);
        $this->addToStat('total_f_fish_yellowperch', $memberStat->f_fish_yellowperch);

        $this->addToStat('total_chicken', $memberStat->chicken);
        $this->addToStat('total_bear', $memberStat->bear);
        $this->addToStat('total_boar', $memberStat->boar);
        $this->addToStat('total_polarbear', $memberStat->polarbear);
        $this->addToStat('total_stag', $memberStat->stag);
        $this->addToStat('total_horse', $memberStat->horse);
        $this->addToStat('total_wolf2', $memberStat->wolf2);
        $this->addToStat('total_wolf', $memberStat->wolf);
        $this->addToStat('total_simpleshark', $memberStat->simpleshark);
        $this->addToStat('total_panther', $memberStat->panther);
        $this->addToStat('total_crocodile', $memberStat->crocodile);
        $this->addToStat('total_tiger', $memberStat->tiger);

        $this->addToStat('total_gathered_cloth', $memberStat->gathered_cloth);
        $this->addToStat('total_gathered_pumpkin', $memberStat->gathered_pumpkin);
        $this->addToStat('total_gathered_corn', $memberStat->gathered_corn);
        $this->addToStat('total_gathered_green_berry', $memberStat->gathered_green_berry);
        $this->addToStat('total_gathered_blue_berry', $memberStat->gathered_blue_berry);
        $this->addToStat('total_gathered_yellow_berry', $memberStat->gathered_yellow_berry);
        $this->addToStat('total_gathered_red_berry', $memberStat->gathered_red_berry);
        $this->addToStat('total_gathered_white_berry', $memberStat->gathered_white_berry);
        $this->addToStat('total_gathered_black_berry', $memberStat->gathered_black_berry);
        $this->addToStat('total_gathered_potato', $memberStat->gathered_potato);
        $this->addToStat('total_gathered_orchid', $memberStat->gathered_orchid);
        $this->addToStat('total_gathered_rose', $memberStat->gathered_rose);
        $this->addToStat('total_gathered_sunflower', $memberStat->gathered_sunflower);
        $this->addToStat('total_gathered_wheat', $memberStat->gathered_wheat);

        $this->addToStat('total_playtime', $memberStat->playtime);
        $this->addToStat('total_crate_open', $memberStat->crate_open);
        $this->addToStat('total_barrel', $memberStat->barrel);
        $this->addToStat('total_helicopters', $memberStat->helicopters);
        $this->addToStat('total_bradleys', $memberStat->bradleys);
        $this->addToStat('total_research_table_looted', $memberStat->research_table_looted);
        $this->addToStat('total_excavator_mined', $memberStat->excavator_mined);
    }

    protected function calculateTopRatings(): void
    {
        $rating = UserTop::getRaiting();

        $this->setStatValue('top_reider', round(
            $this->getStatValue('total_c4thrown') * ($rating[UserTop::TYPE_REIDER]['c4thrown'] ?? 1) +
            $this->getStatValue('total_satchelsthrown') * ($rating[UserTop::TYPE_REIDER]['satchelsthrown'] ?? 0.2) +
            $this->getStatValue('total_rocket_basic') * ($rating[UserTop::TYPE_REIDER]['rocket_basic'] ?? 0.5) +
            $this->getStatValue('total_rocket_hv') * ($rating[UserTop::TYPE_REIDER]['rocket_hv'] ?? 0.1) +
            $this->getStatValue('total_rocket_fire') * ($rating[UserTop::TYPE_REIDER]['rocket_fire'] ?? 0.1) +
            $this->getStatValue('total_ammo_explosive') * ($rating[UserTop::TYPE_REIDER]['ammo_explosive'] ?? 0.01) +
            $this->getStatValue('total_grenade_f1_deployed') * ($rating[UserTop::TYPE_REIDER]['grenade.f1.deployed'] ?? 0.02) +
            $this->getStatValue('total_grenade_molotov_deployed') * ($rating[UserTop::TYPE_REIDER]['grenade.molotov.deployed'] ?? 0.05) +
            $this->getStatValue('total_grenade_beancan_deployed') * ($rating[UserTop::TYPE_REIDER]['grenade.beancan.deployed'] ?? 0.05)
        ));

        $this->setStatValue('top_kills', $this->getStatValue('total_kills'));
        $this->setStatValue('top_scientists', $this->getStatValue('total_scientists'));
        $this->setStatValue('top_playtime', $this->getStatValue('total_playtime'));

        $this->setStatValue('top_farmer', round(
            $this->getStatValue('total_wood') * ($rating[UserTop::TYPE_FARMER]['wood'] ?? 0.05) +
            $this->getStatValue('total_stones') * ($rating[UserTop::TYPE_FARMER]['stones'] ?? 0.3) +
            $this->getStatValue('total_metal_ore') * ($rating[UserTop::TYPE_FARMER]['metal.ore'] ?? 0.5) +
            $this->getStatValue('total_sulfur_ore') * ($rating[UserTop::TYPE_FARMER]['sulfur.ore'] ?? 1)
        ));

        $this->setStatValue('top_fishing', round(
            $this->getStatValue('total_f_fish_anchovy') * ($rating[UserTop::TYPE_FISHING]['f_fish.anchovy'] ?? 10) +
            $this->getStatValue('total_f_fish_catfish') * ($rating[UserTop::TYPE_FISHING]['f_fish.catfish'] ?? 32) +
            $this->getStatValue('total_f_fish_herring') * ($rating[UserTop::TYPE_FISHING]['f_fish.herring'] ?? 10) +
            $this->getStatValue('total_f_fish_orangeroughy') * ($rating[UserTop::TYPE_FISHING]['f_fish.orangeroughy'] ?? 37) +
            $this->getStatValue('total_f_fish_salmon') * ($rating[UserTop::TYPE_FISHING]['f_fish.salmon'] ?? 22) +
            $this->getStatValue('total_f_fish_sardine') * ($rating[UserTop::TYPE_FISHING]['f_fish.sardine'] ?? 10) +
            $this->getStatValue('total_f_fish_smallshark') * ($rating[UserTop::TYPE_FISHING]['f_fish.smallshark'] ?? 45) +
            $this->getStatValue('total_f_fish_troutsmall') * ($rating[UserTop::TYPE_FISHING]['f_fish.troutsmall'] ?? 15) +
            $this->getStatValue('total_f_fish_yellowperch') * ($rating[UserTop::TYPE_FISHING]['f_fish.yellowperch'] ?? 25)
        ));

        $this->setStatValue('top_hunter',
            $this->getStatValue('total_chicken') +
            $this->getStatValue('total_bear') +
            $this->getStatValue('total_boar') +
            $this->getStatValue('total_polarbear') +
            $this->getStatValue('total_stag') +
            $this->getStatValue('total_horse') +
            $this->getStatValue('total_wolf2') +
            $this->getStatValue('total_wolf') +
            $this->getStatValue('total_simpleshark') +
            $this->getStatValue('total_panther') +
            $this->getStatValue('total_crocodile') +
            $this->getStatValue('total_tiger')
        );

        $this->setStatValue('top_fermer', round(
            $this->getStatValue('total_gathered_cloth') * ($rating[UserTop::TYPE_FERMER]['gathered_cloth'] ?? 0.05) +
            $this->getStatValue('total_gathered_pumpkin') * ($rating[UserTop::TYPE_FERMER]['gathered_pumpkin'] ?? 0.5) +
            $this->getStatValue('total_gathered_corn') * ($rating[UserTop::TYPE_FERMER]['gathered_corn'] ?? 0.3) +
            $this->getStatValue('total_gathered_green_berry') * ($rating[UserTop::TYPE_FERMER]['gathered_green.berry'] ?? 0.5) +
            $this->getStatValue('total_gathered_blue_berry') * ($rating[UserTop::TYPE_FERMER]['gathered_blue.berry'] ?? 0.5) +
            $this->getStatValue('total_gathered_yellow_berry') * ($rating[UserTop::TYPE_FERMER]['gathered_yellow.berry'] ?? 0.5) +
            $this->getStatValue('total_gathered_red_berry') * ($rating[UserTop::TYPE_FERMER]['gathered_red.berry'] ?? 0.5) +
            $this->getStatValue('total_gathered_white_berry') * ($rating[UserTop::TYPE_FERMER]['gathered_white.berry'] ?? 0.5) +
            $this->getStatValue('total_gathered_black_berry') * ($rating[UserTop::TYPE_FERMER]['gathered_black.berry'] ?? 1) +
            $this->getStatValue('total_gathered_potato') * ($rating[UserTop::TYPE_FERMER]['gathered_potato'] ?? 0.4) +
            $this->getStatValue('total_gathered_orchid') * ($rating[UserTop::TYPE_FERMER]['gathered_orchid'] ?? 0.3) +
            $this->getStatValue('total_gathered_rose') * ($rating[UserTop::TYPE_FERMER]['gathered_rose'] ?? 0.3) +
            $this->getStatValue('total_gathered_sunflower') * ($rating[UserTop::TYPE_FERMER]['gathered_sunflower'] ?? 0.3) +
            $this->getStatValue('total_gathered_wheat') * ($rating[UserTop::TYPE_FERMER]['gathered_wheat'] ?? 0.3)
        ));
    }

    /**
     * Вклад участника в клан за вайп: дельта (текущее значение statistics − baseline на момент вступления).
     */
    public static function calculateMemberStatistics($member, $serverId, $wipe): array
    {
        $server = Servers::findOne($serverId);
        if (!$server || $wipe === null || $wipe === '') {
            return [];
        }

        $user = $member->user;
        if (!$user || !$user->steam_id) {
            return [];
        }

        ClanMemberStatsBaseline::ensureBaselineExists($member, $serverId, $wipe);
        $baselineMap = ClanMemberStatsBaseline::getBaselineMap($member->id, $serverId, $wipe);

        $statisticsKeys = ClanMemberStatsBaseline::getTrackedStatKeys();
        $currentMap = ClanMemberStatsBaseline::getCurrentStatisticsValuesMap(
            $user->steam_id,
            $server->tag,
            $wipe,
            $statisticsKeys
        );
        $schema = new ClanMemberStatistics();

        $result = [];
        foreach ($statisticsKeys as $key) {
            $current = (int)($currentMap[$key] ?? 0);
            $base = isset($baselineMap[$key]) ? (int)$baselineMap[$key] : 0;
            $delta = max(0, $current - $base);

            $dbKey = str_replace('.', '_', $key);
            if ($schema->hasAttribute($dbKey)) {
                $result[$dbKey] = $delta;
            }
        }

        return $result;
    }
}
