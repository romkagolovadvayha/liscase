<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use common\models\servers\Servers;
use common\models\user\User;
use common\models\user\UserTop;
use Yii;
use yii\base\UnknownPropertyException;

/**
 * Заголовок статистики участника за вайп; числовые метрики — в [[ClanMemberStatisticsValue]] (stat_key / value).
 *
 * @property int $id
 * @property int $clan_member_id
 * @property int $clan_id
 * @property int $user_id
 * @property int $server_id
 * @property string|null $wipe
 * @property int $updated_at
 * @property string $member_status active|former
 * @property int|null $frozen_at
 *
 * @property ClanMember $clanMember
 * @property Clan $clan
 * @property User $user
 * @property Servers $server
 * @property ClanMemberStatisticsValue[] $statValues
 */
class ClanMemberStatistics extends ActiveRecord
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FORMER = 'former';

    /** @var array<string, float> */
    private $_statsMap = [];

    public static function tableName(): string
    {
        return 'clan_member_statistics';
    }

    /**
     * Ключи из {@see ClanMemberStatsBaseline::getTrackedStatKeys()} в виде stat_key EAV (точки → подчёркивания).
     * Используются при снятии baseline; сами значения дельты в EAV считаются по всем строкам statistics (см. {@see ClanStatistics::calculateMemberStatistics}).
     *
     * @return string[]
     */
    public static function getMemberDeltaStatDbKeys(): array
    {
        $keys = ClanMemberStatsBaseline::getTrackedStatKeys();
        $out = [];
        foreach ($keys as $key) {
            $out[] = str_replace('.', '_', $key);
        }

        return array_values(array_unique($out));
    }

    /**
     * Допустимое имя stat_key в EAV: строчная буква, далее [a-z0-9_].
     */
    public static function isValidMemberStatDbKey(string $dbKey): bool
    {
        return (bool)preg_match('/^[a-z][a-z0-9_]*$/', $dbKey);
    }

    /**
     * Любой ключ stat_key в EAV: буква + буквы/цифры/подчёркивания (kills, top_kills, gathered_green_berry, …).
     */
    private function isMemberStatMagicKey(string $name): bool
    {
        if ($this->hasAttribute($name)) {
            return false;
        }

        return static::isValidMemberStatDbKey($name);
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
        $rows = ClanMemberStatisticsValue::find()
            ->select(['stat_key', 'value'])
            ->where(['clan_member_statistics_id' => $this->id])
            ->asArray()
            ->all();
        foreach ($rows as $row) {
            $this->_statsMap[(string)$row['stat_key']] = (float)$row['value'];
        }
    }

    public function getStatValues(): \yii\db\ActiveQuery
    {
        return $this->hasMany(ClanMemberStatisticsValue::class, ['clan_member_statistics_id' => 'id']);
    }

    public function rules(): array
    {
        return [
            [['clan_member_id', 'clan_id', 'user_id', 'server_id'], 'required'],
            [['member_status'], 'string', 'max' => 20],
            [['frozen_at'], 'integer'],
            [['clan_member_id', 'clan_id', 'user_id', 'server_id', 'updated_at'], 'integer'],
            [['wipe'], 'string', 'max' => 255],
            [['clan_member_id', 'server_id', 'wipe'], 'unique', 'targetAttribute' => ['clan_member_id', 'server_id', 'wipe']],
            [['clan_member_id'], 'exist', 'skipOnError' => true, 'targetClass' => ClanMember::class, 'targetAttribute' => ['clan_member_id' => 'id']],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['server_id'], 'exist', 'skipOnError' => true, 'targetClass' => Servers::class, 'targetAttribute' => ['server_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'clan_member_id' => Yii::t('common', 'Участник клана'),
            'clan_id' => Yii::t('common', 'Клан'),
            'user_id' => Yii::t('common', 'Пользователь'),
            'server_id' => Yii::t('common', 'Сервер'),
            'wipe' => Yii::t('common', 'Вайп'),
            'updated_at' => Yii::t('common', 'Дата обновления'),
        ];
    }

    /**
     * {@inheritdoc}
     * В toArray()/сериализацию включаются и метрики из clan_member_statistics_values.
     */
    public function fields(): array
    {
        $fields = parent::fields();
        foreach (array_keys($this->_statsMap) as $key) {
            $k = $key;
            $fields[$k] = function ($model, $field) use ($k) {
                /** @var self $model */
                return $model->getStatValue($k);
            };
        }

        return $fields;
    }

    public function getClanMember(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ClanMember::class, ['id' => 'clan_member_id']);
    }

    public function getClan(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Clan::class, ['id' => 'clan_id']);
    }

    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getServer(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Servers::class, ['id' => 'server_id']);
    }

    public function getStatValue(string $key): float
    {
        return (float)($this->_statsMap[$key] ?? 0);
    }

    private function setStatValue(string $key, $value): void
    {
        $this->_statsMap[$key] = (float)$value;
    }

    /**
     * Плоский массив для API: заголовок + все stat_key.
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
     * @return array<string, float>
     */
    public function getStatsMap(): array
    {
        return $this->_statsMap;
    }

    /**
     * Перед записью свежих дельт из {@see ClanStatistics::calculateMemberStatistics()} —
     * иначе метрики с нулевой дельтой останутся из предыдущего снимка EAV.
     */
    public function clearMemberStatsMapCache(): void
    {
        $this->_statsMap = [];
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
            throw new \yii\base\InvalidCallException('ClanMemberStatistics must be saved before persisting stat values.');
        }
        ClanMemberStatisticsValue::deleteAll(['clan_member_statistics_id' => $this->id]);
        if ($this->_statsMap === []) {
            return;
        }
        $batch = [];
        foreach ($this->_statsMap as $key => $value) {
            $v = (float)$value;
            if ($v == 0.0) {
                continue;
            }
            $batch[] = [(int)$this->id, (string)$key, $v];
        }
        if ($batch === []) {
            return;
        }
        Yii::$app->db->createCommand()->batchInsert(
            ClanMemberStatisticsValue::tableName(),
            ['clan_member_statistics_id', 'stat_key', 'value'],
            $batch
        )->execute();
    }

    public function __get($name)
    {
        try {
            return parent::__get($name);
        } catch (UnknownPropertyException $e) {
            if ($this->isMemberStatMagicKey($name)) {
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

        return $this->isMemberStatMagicKey($name);
    }

    public function __set($name, $value)
    {
        if ($this->hasAttribute($name)) {
            parent::__set($name, $value);

            return;
        }
        if ($this->isMemberStatMagicKey($name)) {
            $this->setStatValue($name, $value);

            return;
        }
        parent::__set($name, $value);
    }

    public function canSetProperty($name, $checkVars = true, $checkBehaviors = true)
    {
        if ($this->isMemberStatMagicKey($name)) {
            return true;
        }

        return parent::canSetProperty($name, $checkVars, $checkBehaviors);
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

        $statistics->clearMemberStatsMapCache();
        foreach ($statsData as $key => $value) {
            $statistics->$key = $value;
        }

        $statistics->calculateTopRatings();
        $statistics->member_status = self::STATUS_FORMER;
        $statistics->frozen_at = time();
        $statistics->updated_at = time();

        return $statistics->save(false);
    }

    /**
     * Обновление статистики участника
     */
    public static function updateMemberStatistics($member, $serverId, $wipe): bool
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

        $statistics->clearMemberStatsMapCache();
        foreach ($statsData as $key => $value) {
            $statistics->$key = $value;
        }

        $statistics->calculateTopRatings();
        $statistics->member_status = self::STATUS_ACTIVE;
        $statistics->frozen_at = null;
        $statistics->updated_at = time();

        return $statistics->save(false);
    }

    /**
     * Получение статистики участника
     */
    public static function getMemberStatistics($memberId, $serverId, $wipe = null): ?self
    {
        $query = static::find()
            ->where(['clan_member_id' => $memberId, 'server_id' => $serverId]);

        if ($wipe) {
            $query->andWhere(['wipe' => $wipe]);
        }

        return $query->with('statValues')->one();
    }

    /**
     * Сумма по всем вайпам (счётчики суммируются; top_* пропускаются).
     *
     * @return array<string, int|float>
     */
    public static function getTotalStatistics($memberId, $serverId): array
    {
        $statistics = static::find()
            ->where(['clan_member_id' => $memberId, 'server_id' => $serverId])
            ->all();

        $total = [];
        foreach ($statistics as $stat) {
            foreach ($stat->getStatsMap() as $k => $v) {
                if (strpos((string)$k, 'top_') === 0) {
                    continue;
                }
                $total[$k] = ($total[$k] ?? 0) + (int)$v;
            }
        }

        return $total;
    }

    /**
     * Расчет рейтингов для топов на основе сохраненных данных
     */
    public function calculateTopRatings(): void
    {
        $rating = UserTop::getRaiting();

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

        $this->top_kills = $this->kills;
        $this->top_scientists = $this->scientists;
        $this->top_playtime = $this->playtime;

        $this->top_farmer = round(
            $this->wood * ($rating[UserTop::TYPE_FARMER]['wood'] ?? 0.05) +
            $this->stones * ($rating[UserTop::TYPE_FARMER]['stones'] ?? 0.3) +
            $this->metal_ore * ($rating[UserTop::TYPE_FARMER]['metal.ore'] ?? 0.5) +
            $this->sulfur_ore * ($rating[UserTop::TYPE_FARMER]['sulfur.ore'] ?? 1)
        );

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
