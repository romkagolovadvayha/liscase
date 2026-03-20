<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use common\models\servers\Servers;
use common\models\user\UserTop;
use Yii;

/**
 * This is the model class for table "clan_statistics".
 *
 * @property int $id
 * @property int $clan_id
 * @property int $server_id
 * @property string|null $wipe
 * @property int $total_kills
 * @property int $total_deaths
 * @property float $top_reider
 * @property float $top_kills
 * @property float $top_scientists
 * @property float $top_playtime
 * @property float $top_farmer
 * @property float $top_fishing
 * @property float $top_hunter
 * @property float $top_fermer
 * @property int $updated_at
 *
 * @property Clan $clan
 * @property Servers $server
 */
class ClanStatistics extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clan_statistics';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['clan_id', 'server_id'], 'required'],
            [['clan_id', 'server_id', 'total_kills', 'total_deaths', 'total_scientists', 'total_wounded', 'total_tcs_destroyed', 'total_nude_kills'], 'integer'],
            [['total_helicopters', 'total_bradleys', 'total_research_table_looted', 'total_excavator_mined'], 'integer'],
            [['top_reider', 'top_kills', 'top_scientists', 'top_playtime', 'top_farmer', 'top_fishing', 'top_hunter', 'top_fermer'], 'number'],
            [['wipe'], 'string', 'max' => 255],
            [['last_activity_date'], 'safe'],
            [['clan_id', 'server_id', 'wipe'], 'unique', 'targetAttribute' => ['clan_id', 'server_id', 'wipe']],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'clan_id' => Yii::t('common', 'Клан'),
            'server_id' => Yii::t('common', 'Сервер'),
            'wipe' => Yii::t('common', 'Вайп'),
            'total_kills' => Yii::t('common', 'Всего убийств'),
            'total_deaths' => Yii::t('common', 'Всего смертей'),
            'updated_at' => Yii::t('common', 'Дата обновления'),
        ];
    }

    /**
     * Gets query for [[Clan]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClan()
    {
        return $this->hasOne(Clan::class, ['id' => 'clan_id']);
    }

    /**
     * Gets query for [[Server]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getServer()
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
    }

    /**
     * Обновление статистики клана
     *
     * @return bool
     */
    public function updateStatistics()
    {
        // Получаем все индивидуальные статистики участников для этого вайпа
        $memberStatistics = ClanMemberStatistics::find()
            ->where(['clan_id' => $this->clan_id, 'server_id' => $this->server_id, 'wipe' => $this->wipe])
            ->all();

        // Обнуляем все поля
        $this->resetStatistics();

        // Суммируем статистику всех участников
        foreach ($memberStatistics as $memberStat) {
            $this->aggregateMemberStatistics($memberStat);
        }

        // Рассчитываем рейтинги для топов
        $this->calculateTopRatings();

        return $this->save(false);
    }

    /**
     * Обнуление статистики
     *
     * @return void
     */
    protected function resetStatistics()
    {
        // Боевая статистика
        $this->total_kills = 0;
        $this->total_deaths = 0;
        $this->total_scientists = 0;
        $this->total_wounded = 0;
        $this->total_tcs_destroyed = 0;
        $this->total_nude_kills = 0;

        // Попадания
        $this->total_hits_head = 0;
        $this->total_hits_neck = 0;
        $this->total_hits_chest = 0;
        $this->total_hits_lowerspine = 0;
        $this->total_hits_lefthand = 0;
        $this->total_hits_leftleg = 0;
        $this->total_hits_leftfoot = 0;
        $this->total_hits_righthand = 0;
        $this->total_hits_rightleg = 0;
        $this->total_hits_rightfoot = 0;

        // Рейдер
        $this->total_c4thrown = 0;
        $this->total_satchelsthrown = 0;
        $this->total_rocket_basic = 0;
        $this->total_rocket_hv = 0;
        $this->total_rocket_fire = 0;
        $this->total_ammo_explosive = 0;
        $this->total_grenade_f1_deployed = 0;
        $this->total_grenade_molotov_deployed = 0;
        $this->total_grenade_beancan_deployed = 0;

        // Фармер
        $this->total_wood = 0;
        $this->total_stones = 0;
        $this->total_metal_ore = 0;
        $this->total_sulfur_ore = 0;

        // Рыбак
        $this->total_f_fish_anchovy = 0;
        $this->total_f_fish_catfish = 0;
        $this->total_f_fish_herring = 0;
        $this->total_f_fish_orangeroughy = 0;
        $this->total_f_fish_salmon = 0;
        $this->total_f_fish_sardine = 0;
        $this->total_f_fish_smallshark = 0;
        $this->total_f_fish_troutsmall = 0;
        $this->total_f_fish_yellowperch = 0;

        // Охотник
        $this->total_chicken = 0;
        $this->total_bear = 0;
        $this->total_boar = 0;
        $this->total_polarbear = 0;
        $this->total_stag = 0;
        $this->total_horse = 0;
        $this->total_wolf2 = 0;
        $this->total_wolf = 0;
        $this->total_simpleshark = 0;
        $this->total_panther = 0;
        $this->total_crocodile = 0;
        $this->total_tiger = 0;

        // Фермер
        $this->total_gathered_cloth = 0;
        $this->total_gathered_pumpkin = 0;
        $this->total_gathered_corn = 0;
        $this->total_gathered_green_berry = 0;
        $this->total_gathered_blue_berry = 0;
        $this->total_gathered_yellow_berry = 0;
        $this->total_gathered_red_berry = 0;
        $this->total_gathered_white_berry = 0;
        $this->total_gathered_black_berry = 0;
        $this->total_gathered_potato = 0;
        $this->total_gathered_orchid = 0;
        $this->total_gathered_rose = 0;
        $this->total_gathered_sunflower = 0;
        $this->total_gathered_wheat = 0;

        // Другое
        $this->total_playtime = 0;
        $this->total_crate_open = 0;
        $this->total_barrel = 0;
        $this->total_helicopters = 0;
        $this->total_bradleys = 0;
        $this->total_research_table_looted = 0;
        $this->total_excavator_mined = 0;
    }

    /**
     * Агрегация статистики участника
     *
     * @param ClanMemberStatistics $memberStat
     * @return void
     */
    protected function aggregateMemberStatistics($memberStat)
    {
        // Боевая статистика
        $this->total_kills += $memberStat->kills;
        $this->total_deaths += $memberStat->deaths;
        $this->total_scientists += $memberStat->scientists;
        $this->total_wounded += $memberStat->wounded;
        $this->total_tcs_destroyed += $memberStat->tcs_destroyed;
        $this->total_nude_kills += $memberStat->nude_kills;

        // Попадания
        $this->total_hits_head += $memberStat->hits_head;
        $this->total_hits_neck += $memberStat->hits_neck;
        $this->total_hits_chest += $memberStat->hits_chest;
        $this->total_hits_lowerspine += $memberStat->hits_lowerspine;
        $this->total_hits_lefthand += $memberStat->hits_lefthand;
        $this->total_hits_leftleg += $memberStat->hits_leftleg;
        $this->total_hits_leftfoot += $memberStat->hits_leftfoot;
        $this->total_hits_righthand += $memberStat->hits_righthand;
        $this->total_hits_rightleg += $memberStat->hits_rightleg;
        $this->total_hits_rightfoot += $memberStat->hits_rightfoot;

        // Рейдер
        $this->total_c4thrown += $memberStat->c4thrown;
        $this->total_satchelsthrown += $memberStat->satchelsthrown;
        $this->total_rocket_basic += $memberStat->rocket_basic;
        $this->total_rocket_hv += $memberStat->rocket_hv;
        $this->total_rocket_fire += $memberStat->rocket_fire;
        $this->total_ammo_explosive += $memberStat->ammo_explosive;
        $this->total_grenade_f1_deployed += $memberStat->grenade_f1_deployed;
        $this->total_grenade_molotov_deployed += $memberStat->grenade_molotov_deployed;
        $this->total_grenade_beancan_deployed += $memberStat->grenade_beancan_deployed;

        // Фармер
        $this->total_wood += $memberStat->wood;
        $this->total_stones += $memberStat->stones;
        $this->total_metal_ore += $memberStat->metal_ore;
        $this->total_sulfur_ore += $memberStat->sulfur_ore;

        // Рыбак
        $this->total_f_fish_anchovy += $memberStat->f_fish_anchovy;
        $this->total_f_fish_catfish += $memberStat->f_fish_catfish;
        $this->total_f_fish_herring += $memberStat->f_fish_herring;
        $this->total_f_fish_orangeroughy += $memberStat->f_fish_orangeroughy;
        $this->total_f_fish_salmon += $memberStat->f_fish_salmon;
        $this->total_f_fish_sardine += $memberStat->f_fish_sardine;
        $this->total_f_fish_smallshark += $memberStat->f_fish_smallshark;
        $this->total_f_fish_troutsmall += $memberStat->f_fish_troutsmall;
        $this->total_f_fish_yellowperch += $memberStat->f_fish_yellowperch;

        // Охотник
        $this->total_chicken += $memberStat->chicken;
        $this->total_bear += $memberStat->bear;
        $this->total_boar += $memberStat->boar;
        $this->total_polarbear += $memberStat->polarbear;
        $this->total_stag += $memberStat->stag;
        $this->total_horse += $memberStat->horse;
        $this->total_wolf2 += $memberStat->wolf2;
        $this->total_wolf += $memberStat->wolf;
        $this->total_simpleshark += $memberStat->simpleshark;
        $this->total_panther += $memberStat->panther;
        $this->total_crocodile += $memberStat->crocodile;
        $this->total_tiger += $memberStat->tiger;

        // Фермер
        $this->total_gathered_cloth += $memberStat->gathered_cloth;
        $this->total_gathered_pumpkin += $memberStat->gathered_pumpkin;
        $this->total_gathered_corn += $memberStat->gathered_corn;
        $this->total_gathered_green_berry += $memberStat->gathered_green_berry;
        $this->total_gathered_blue_berry += $memberStat->gathered_blue_berry;
        $this->total_gathered_yellow_berry += $memberStat->gathered_yellow_berry;
        $this->total_gathered_red_berry += $memberStat->gathered_red_berry;
        $this->total_gathered_white_berry += $memberStat->gathered_white_berry;
        $this->total_gathered_black_berry += $memberStat->gathered_black_berry;
        $this->total_gathered_potato += $memberStat->gathered_potato;
        $this->total_gathered_orchid += $memberStat->gathered_orchid;
        $this->total_gathered_rose += $memberStat->gathered_rose;
        $this->total_gathered_sunflower += $memberStat->gathered_sunflower;
        $this->total_gathered_wheat += $memberStat->gathered_wheat;

        // Другое
        $this->total_playtime += $memberStat->playtime;
        $this->total_crate_open += $memberStat->crate_open;
        $this->total_barrel += $memberStat->barrel;
        $this->total_helicopters += $memberStat->helicopters;
        $this->total_bradleys += $memberStat->bradleys;
        $this->total_research_table_looted += $memberStat->research_table_looted;
        $this->total_excavator_mined += $memberStat->excavator_mined;
    }

    /**
     * Расчет рейтингов для топов
     *
     * @return void
     */
    protected function calculateTopRatings()
    {
        $rating = UserTop::getRaiting();

        // Рейдер
        $this->top_reider = round(
            $this->total_c4thrown * ($rating[UserTop::TYPE_REIDER]['c4thrown'] ?? 1) +
            $this->total_satchelsthrown * ($rating[UserTop::TYPE_REIDER]['satchelsthrown'] ?? 0.2) +
            ($this->total_rocket_basic) * ($rating[UserTop::TYPE_REIDER]['rocket_basic'] ?? 0.5) +
            ($this->total_rocket_hv) * ($rating[UserTop::TYPE_REIDER]['rocket_hv'] ?? 0.1) +
            ($this->total_rocket_fire) * ($rating[UserTop::TYPE_REIDER]['rocket_fire'] ?? 0.1) +
            $this->total_ammo_explosive * ($rating[UserTop::TYPE_REIDER]['ammo_explosive'] ?? 0.01) +
            $this->total_grenade_f1_deployed * ($rating[UserTop::TYPE_REIDER]['grenade.f1.deployed'] ?? 0.02) +
            $this->total_grenade_molotov_deployed * ($rating[UserTop::TYPE_REIDER]['grenade.molotov.deployed'] ?? 0.05) +
            $this->total_grenade_beancan_deployed * ($rating[UserTop::TYPE_REIDER]['grenade.beancan.deployed'] ?? 0.05)
        );

        // Kills
        $this->top_kills = $this->total_kills;

        // Scientists
        $this->top_scientists = $this->total_scientists;

        // Playtime
        $this->top_playtime = $this->total_playtime;

        // Farmer
        $this->top_farmer = round(
            $this->total_wood * ($rating[UserTop::TYPE_FARMER]['wood'] ?? 0.05) +
            $this->total_stones * ($rating[UserTop::TYPE_FARMER]['stones'] ?? 0.3) +
            $this->total_metal_ore * ($rating[UserTop::TYPE_FARMER]['metal.ore'] ?? 0.5) +
            $this->total_sulfur_ore * ($rating[UserTop::TYPE_FARMER]['sulfur.ore'] ?? 1)
        );

        // Fishing
        $this->top_fishing = round(
            $this->total_f_fish_anchovy * ($rating[UserTop::TYPE_FISHING]['f_fish.anchovy'] ?? 10) +
            $this->total_f_fish_catfish * ($rating[UserTop::TYPE_FISHING]['f_fish.catfish'] ?? 32) +
            $this->total_f_fish_herring * ($rating[UserTop::TYPE_FISHING]['f_fish.herring'] ?? 10) +
            $this->total_f_fish_orangeroughy * ($rating[UserTop::TYPE_FISHING]['f_fish.orangeroughy'] ?? 37) +
            $this->total_f_fish_salmon * ($rating[UserTop::TYPE_FISHING]['f_fish.salmon'] ?? 22) +
            $this->total_f_fish_sardine * ($rating[UserTop::TYPE_FISHING]['f_fish.sardine'] ?? 10) +
            $this->total_f_fish_smallshark * ($rating[UserTop::TYPE_FISHING]['f_fish.smallshark'] ?? 45) +
            $this->total_f_fish_troutsmall * ($rating[UserTop::TYPE_FISHING]['f_fish.troutsmall'] ?? 15) +
            $this->total_f_fish_yellowperch * ($rating[UserTop::TYPE_FISHING]['f_fish.yellowperch'] ?? 25)
        );

        // Hunter
        $this->top_hunter = 
            $this->total_chicken +
            $this->total_bear +
            $this->total_boar +
            $this->total_polarbear +
            $this->total_stag +
            $this->total_horse +
            $this->total_wolf2 +
            $this->total_wolf +
            $this->total_simpleshark +
            $this->total_panther +
            $this->total_crocodile +
            $this->total_tiger;

        // Fermer
        $this->top_fermer = round(
            $this->total_gathered_cloth * ($rating[UserTop::TYPE_FERMER]['gathered_cloth'] ?? 0.05) +
            $this->total_gathered_pumpkin * ($rating[UserTop::TYPE_FERMER]['gathered_pumpkin'] ?? 0.5) +
            $this->total_gathered_corn * ($rating[UserTop::TYPE_FERMER]['gathered_corn'] ?? 0.3) +
            $this->total_gathered_green_berry * ($rating[UserTop::TYPE_FERMER]['gathered_green.berry'] ?? 0.5) +
            $this->total_gathered_blue_berry * ($rating[UserTop::TYPE_FERMER]['gathered_blue.berry'] ?? 0.5) +
            $this->total_gathered_yellow_berry * ($rating[UserTop::TYPE_FERMER]['gathered_yellow.berry'] ?? 0.5) +
            $this->total_gathered_red_berry * ($rating[UserTop::TYPE_FERMER]['gathered_red.berry'] ?? 0.5) +
            $this->total_gathered_white_berry * ($rating[UserTop::TYPE_FERMER]['gathered_white.berry'] ?? 0.5) +
            $this->total_gathered_black_berry * ($rating[UserTop::TYPE_FERMER]['gathered_black.berry'] ?? 1) +
            $this->total_gathered_potato * ($rating[UserTop::TYPE_FERMER]['gathered_potato'] ?? 0.4) +
            $this->total_gathered_orchid * ($rating[UserTop::TYPE_FERMER]['gathered_orchid'] ?? 0.3) +
            $this->total_gathered_rose * ($rating[UserTop::TYPE_FERMER]['gathered_rose'] ?? 0.3) +
            $this->total_gathered_sunflower * ($rating[UserTop::TYPE_FERMER]['gathered_sunflower'] ?? 0.3) +
            $this->total_gathered_wheat * ($rating[UserTop::TYPE_FERMER]['gathered_wheat'] ?? 0.3)
        );
    }

    /**
     * Подсчет статистики участника за период членства
     *
     * @param ClanMember $member
     * @param int $serverId
     * @param string $wipe
     * @return array
     */
    /**
     * Вклад участника в клан за вайп: дельта (текущее значение statistics − baseline на момент вступления).
     * В statistics одна накопительная строка на ключ за вайп, поэтому без baseline «до вступления» не отделить.
     */
    public static function calculateMemberStatistics($member, $serverId, $wipe)
    {
        $server = \common\models\servers\Servers::findOne($serverId);
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

