<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use common\models\servers\Servers;
use common\models\user\User;
use common\models\user\UserTop;
use Yii;

/**
 * This is the model class for table "clan_member_statistics".
 *
 * @property int $id
 * @property int $clan_member_id
 * @property int $clan_id
 * @property int $user_id
 * @property int $server_id
 * @property string|null $wipe
 * @property int $kills
 * @property int $deaths
 * @property float $top_reider
 * @property float $top_kills
 * @property float $top_scientists
 * @property float $top_playtime
 * @property float $top_farmer
 * @property float $top_fishing
 * @property float $top_hunter
 * @property float $top_fermer
 * @property int $updated_at
 * @property string $member_status active|former
 * @property int|null $frozen_at
 *
 * @property ClanMember $clanMember
 * @property Clan $clan
 * @property User $user
 * @property Servers $server
 */
class ClanMemberStatistics extends ActiveRecord
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FORMER = 'former';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clan_member_statistics';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['clan_member_id', 'clan_id', 'user_id', 'server_id'], 'required'],
            [['member_status'], 'string', 'max' => 20],
            [['frozen_at'], 'integer'],
            [['clan_member_id', 'clan_id', 'user_id', 'server_id', 'kills', 'deaths', 'scientists', 'wounded', 'tcs_destroyed', 'nude_kills'], 'integer'],
            [['helicopters', 'bradleys', 'research_table_looted', 'excavator_mined'], 'integer'],
            [['top_reider', 'top_kills', 'top_scientists', 'top_playtime', 'top_farmer', 'top_fishing', 'top_hunter', 'top_fermer'], 'number'],
            [['wipe'], 'string', 'max' => 255],
            [['clan_member_id', 'server_id', 'wipe'], 'unique', 'targetAttribute' => ['clan_member_id', 'server_id', 'wipe']],
            [['clan_member_id'], 'exist', 'skipOnError' => true, 'targetClass' => ClanMember::class, 'targetAttribute' => ['clan_member_id' => 'id']],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
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
            'clan_member_id' => Yii::t('common', 'Участник клана'),
            'clan_id' => Yii::t('common', 'Клан'),
            'user_id' => Yii::t('common', 'Пользователь'),
            'server_id' => Yii::t('common', 'Сервер'),
            'wipe' => Yii::t('common', 'Вайп'),
            'kills' => Yii::t('common', 'Убийства'),
            'deaths' => Yii::t('common', 'Смерти'),
            'updated_at' => Yii::t('common', 'Дата обновления'),
        ];
    }

    /**
     * Gets query for [[ClanMember]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClanMember()
    {
        return $this->hasOne(ClanMember::class, ['id' => 'clan_member_id']);
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
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
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
     * Участник относится к этому вайпу (не ушёл до начала вайпа и не вступил после конца).
     */
    public static function isMemberRelevantForWipe(ClanMember $member, Servers $server, string $wipe): bool
    {
        $parts = explode('/', $wipe, 2);
        if (count($parts) < 2) {
            return true;
        }

        $wipeStart = strtotime(trim($parts[0]) . ' 00:00:00');
        $wipeEnd = strtotime(trim($parts[1]) . ' 23:59:59');
        if ($wipeStart === false) {
            return true;
        }
        if ($wipeEnd === false) {
            $wipeEnd = PHP_INT_MAX;
        }

        $joinTs = strtotime($member->join_date);
        if ($joinTs > $wipeEnd) {
            return false;
        }

        if ($member->leave_date) {
            $leaveTs = strtotime($member->leave_date);
            if ($leaveTs < $wipeStart) {
                return false;
            }
        }

        return true;
    }

    /**
     * Заморозить строку статистики за вайп (бывший участник): дельта на момент выхода, дальше не обновлять.
     */
    public static function finalizeAndFreeze(ClanMember $member, int $serverId, string $wipe): bool
    {
        $server = Servers::findOne($serverId);
        if (!$server || !static::isMemberRelevantForWipe($member, $server, $wipe)) {
            return true;
        }

        $statistics = static::find()
            ->where([
                'clan_member_id' => $member->id,
                'server_id' => $serverId,
                'wipe' => $wipe,
            ])
            ->one();

        if ($statistics && $statistics->frozen_at) {
            return true;
        }

        $statsData = ClanStatistics::calculateMemberStatistics($member, $serverId, $wipe);

        if (!$statistics) {
            $statistics = new static();
            $statistics->clan_member_id = $member->id;
            $statistics->clan_id = $member->clan_id;
            $statistics->user_id = $member->user_id;
            $statistics->server_id = $serverId;
            $statistics->wipe = $wipe;
        }

        foreach ($statsData as $key => $value) {
            if ($statistics->hasAttribute($key)) {
                $statistics->$key = $value;
            }
        }

        $statistics->calculateTopRatings();
        $statistics->member_status = self::STATUS_FORMER;
        $statistics->frozen_at = time();
        $statistics->updated_at = time();

        return $statistics->save(false);
    }

    /**
     * Обновление статистики участника
     *
     * @param ClanMember $member
     * @param int $serverId
     * @param string $wipe
     * @return bool
     */
    public static function updateMemberStatistics($member, $serverId, $wipe)
    {
        $server = Servers::findOne($serverId);
        if (!$server || $wipe === null || $wipe === '' || !static::isMemberRelevantForWipe($member, $server, $wipe)) {
            return true;
        }

        $statistics = static::find()
            ->where([
                'clan_member_id' => $member->id,
                'server_id' => $serverId,
                'wipe' => $wipe,
            ])
            ->one();

        if ($statistics && $statistics->frozen_at) {
            return true;
        }

        if ($member->leave_date) {
            return static::finalizeAndFreeze($member, $serverId, $wipe);
        }

        $statsData = ClanStatistics::calculateMemberStatistics($member, $serverId, $wipe);

        if (!$statistics) {
            $statistics = new static();
            $statistics->clan_member_id = $member->id;
            $statistics->clan_id = $member->clan_id;
            $statistics->user_id = $member->user_id;
            $statistics->server_id = $serverId;
            $statistics->wipe = $wipe;
        }

        foreach ($statsData as $key => $value) {
            if ($statistics->hasAttribute($key)) {
                $statistics->$key = $value;
            }
        }

        $statistics->calculateTopRatings();
        $statistics->member_status = self::STATUS_ACTIVE;
        $statistics->frozen_at = null;
        $statistics->updated_at = time();

        return $statistics->save(false);
    }

    /**
     * Получение статистики участника
     *
     * @param int $memberId
     * @param int $serverId
     * @param string|null $wipe
     * @return static|null
     */
    public static function getMemberStatistics($memberId, $serverId, $wipe = null)
    {
        $query = static::find()
            ->where(['clan_member_id' => $memberId, 'server_id' => $serverId]);

        if ($wipe) {
            $query->andWhere(['wipe' => $wipe]);
        }

        return $query->one();
    }

    /**
     * Общая статистика участника по всем вайпам
     *
     * @param int $memberId
     * @param int $serverId
     * @return array
     */
    public static function getTotalStatistics($memberId, $serverId)
    {
        $statistics = static::find()
            ->where(['clan_member_id' => $memberId, 'server_id' => $serverId])
            ->all();

        $total = [
            'kills' => 0,
            'deaths' => 0,
            'scientists' => 0,
            'wounded' => 0,
            'tcs_destroyed' => 0,
            'nude_kills' => 0,
            'playtime' => 0,
            'crate_open' => 0,
            'barrel' => 0,
            'helicopters' => 0,
            'bradleys' => 0,
            'research_table_looted' => 0,
            'excavator_mined' => 0,
        ];

        foreach ($statistics as $stat) {
            $total['kills'] += $stat->kills;
            $total['deaths'] += $stat->deaths;
            $total['scientists'] += $stat->scientists;
            $total['wounded'] += $stat->wounded;
            $total['tcs_destroyed'] += $stat->tcs_destroyed;
            $total['nude_kills'] += $stat->nude_kills;
            $total['playtime'] += $stat->playtime;
            $total['crate_open'] += $stat->crate_open;
            $total['barrel'] += $stat->barrel;
            $total['helicopters'] += $stat->helicopters;
            $total['bradleys'] += $stat->bradleys;
            $total['research_table_looted'] += $stat->research_table_looted;
            $total['excavator_mined'] += $stat->excavator_mined;
        }

        return $total;
    }

    /**
     * Расчет рейтингов для топов на основе сохраненных данных
     *
     * @return void
     */
    public function calculateTopRatings()
    {
        $rating = UserTop::getRaiting();

        // Рейдер
        $this->top_reider = round(
            $this->c4thrown * ($rating[UserTop::TYPE_REIDER]['c4thrown'] ?? 1) +
            $this->satchelsthrown * ($rating[UserTop::TYPE_REIDER]['satchelsthrown'] ?? 0.2) +
            ($this->rocket_basic) * ($rating[UserTop::TYPE_REIDER]['rocket_basic'] ?? 0.5) +
            ($this->rocket_hv) * ($rating[UserTop::TYPE_REIDER]['rocket_hv'] ?? 0.1) +
            ($this->rocket_fire) * ($rating[UserTop::TYPE_REIDER]['rocket_fire'] ?? 0.1) +
            $this->ammo_explosive * ($rating[UserTop::TYPE_REIDER]['ammo_explosive'] ?? 0.01) +
            $this->grenade_f1_deployed * ($rating[UserTop::TYPE_REIDER]['grenade.f1.deployed'] ?? 0.02) +
            $this->grenade_molotov_deployed * ($rating[UserTop::TYPE_REIDER]['grenade.molotov.deployed'] ?? 0.05) +
            $this->grenade_beancan_deployed * ($rating[UserTop::TYPE_REIDER]['grenade.beancan.deployed'] ?? 0.05)
        );

        // Kills
        $this->top_kills = $this->kills;

        // Scientists
        $this->top_scientists = $this->scientists;

        // Playtime
        $this->top_playtime = $this->playtime;

        // Farmer
        $this->top_farmer = round(
            $this->wood * ($rating[UserTop::TYPE_FARMER]['wood'] ?? 0.05) +
            $this->stones * ($rating[UserTop::TYPE_FARMER]['stones'] ?? 0.3) +
            $this->metal_ore * ($rating[UserTop::TYPE_FARMER]['metal.ore'] ?? 0.5) +
            $this->sulfur_ore * ($rating[UserTop::TYPE_FARMER]['sulfur.ore'] ?? 1)
        );

        // Fishing
        $this->top_fishing = round(
            $this->f_fish_anchovy * ($rating[UserTop::TYPE_FISHING]['f_fish.anchovy'] ?? 10) +
            $this->f_fish_catfish * ($rating[UserTop::TYPE_FISHING]['f_fish.catfish'] ?? 32) +
            $this->f_fish_herring * ($rating[UserTop::TYPE_FISHING]['f_fish.herring'] ?? 10) +
            $this->f_fish_orangeroughy * ($rating[UserTop::TYPE_FISHING]['f_fish.orangeroughy'] ?? 37) +
            $this->f_fish_salmon * ($rating[UserTop::TYPE_FISHING]['f_fish.salmon'] ?? 22) +
            $this->f_fish_sardine * ($rating[UserTop::TYPE_FISHING]['f_fish.sardine'] ?? 10) +
            $this->f_fish_smallshark * ($rating[UserTop::TYPE_FISHING]['f_fish.smallshark'] ?? 45) +
            $this->f_fish_troutsmall * ($rating[UserTop::TYPE_FISHING]['f_fish.troutsmall'] ?? 15) +
            $this->f_fish_yellowperch * ($rating[UserTop::TYPE_FISHING]['f_fish.yellowperch'] ?? 25)
        );

        // Hunter
        $this->top_hunter = 
            $this->chicken +
            $this->bear +
            $this->boar +
            $this->polarbear +
            $this->stag +
            $this->horse +
            $this->wolf2 +
            $this->wolf +
            $this->simpleshark +
            $this->panther +
            $this->crocodile +
            $this->tiger;

        // Fermer
        $this->top_fermer = round(
            $this->gathered_cloth * ($rating[UserTop::TYPE_FERMER]['gathered_cloth'] ?? 0.05) +
            $this->gathered_pumpkin * ($rating[UserTop::TYPE_FERMER]['gathered_pumpkin'] ?? 0.5) +
            $this->gathered_corn * ($rating[UserTop::TYPE_FERMER]['gathered_corn'] ?? 0.3) +
            $this->gathered_green_berry * ($rating[UserTop::TYPE_FERMER]['gathered_green.berry'] ?? 0.5) +
            $this->gathered_blue_berry * ($rating[UserTop::TYPE_FERMER]['gathered_blue.berry'] ?? 0.5) +
            $this->gathered_yellow_berry * ($rating[UserTop::TYPE_FERMER]['gathered_yellow.berry'] ?? 0.5) +
            $this->gathered_red_berry * ($rating[UserTop::TYPE_FERMER]['gathered_red.berry'] ?? 0.5) +
            $this->gathered_white_berry * ($rating[UserTop::TYPE_FERMER]['gathered_white.berry'] ?? 0.5) +
            $this->gathered_black_berry * ($rating[UserTop::TYPE_FERMER]['gathered_black.berry'] ?? 1) +
            $this->gathered_potato * ($rating[UserTop::TYPE_FERMER]['gathered_potato'] ?? 0.4) +
            $this->gathered_orchid * ($rating[UserTop::TYPE_FERMER]['gathered_orchid'] ?? 0.3) +
            $this->gathered_rose * ($rating[UserTop::TYPE_FERMER]['gathered_rose'] ?? 0.3) +
            $this->gathered_sunflower * ($rating[UserTop::TYPE_FERMER]['gathered_sunflower'] ?? 0.3) +
            $this->gathered_wheat * ($rating[UserTop::TYPE_FERMER]['gathered_wheat'] ?? 0.3)
        );
    }
}

