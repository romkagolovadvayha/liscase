<?php

namespace common\models\media;

use common\components\base\ActiveRecord;
use common\models\profit\Profit;
use common\models\user\User;
use Yii;

/**
 * Запись об эфире стримера (Twitch/Kick).
 *
 * @property int $id
 * @property int $user_id
 * @property string $started_at
 * @property string|null $ended_at
 * @property int|null $duration_minutes
 * @property int $status 1 — идёт, 2 — закончился
 * @property int $bonus_coins
 * @property string $platform twitch|kick
 * @property string $created_at
 * @property string|null $updated_at
 *
 * @property-read User $user
 */
class MediaLive extends ActiveRecord
{
    public const STATUS_LIVE = 1;
    public const STATUS_ENDED = 2;

    public const PLATFORM_TWITCH = 'twitch';
    public const PLATFORM_KICK = 'kick';

    /** Монет за каждый полный блок минут */
    public const COINS_PER_BLOCK = 30;
    /** Длительность блока в минутах */
    public const MINUTES_PER_BLOCK = 30;

    public static function tableName(): string
    {
        return '{{%media_live}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'started_at', 'status', 'platform', 'created_at'], 'required'],
            [['user_id', 'duration_minutes', 'status', 'bonus_coins'], 'integer'],
            [['started_at', 'ended_at', 'created_at', 'updated_at'], 'safe'],
            [['platform'], 'string', 'max' => 8],
            [['platform'], 'in', 'range' => [self::PLATFORM_TWITCH, self::PLATFORM_KICK]],
            [['status'], 'in', 'range' => [self::STATUS_LIVE, self::STATUS_ENDED]],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public static function findActiveForUser(int $userId): ?self
    {
        /** @var self|null $row */
        $row = static::find()
            ->where(['user_id' => $userId, 'status' => self::STATUS_LIVE])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $row;
    }

    /**
     * Закрыть эфир: длительность, бонус, при необходимости начисление на лицевой счёт.
     */
    public function finalize(string $endedAt, bool $awardBonus = true): void
    {
        if ((int) $this->status !== self::STATUS_LIVE) {
            return;
        }

        $startTs = strtotime($this->started_at);
        $endTs = strtotime($endedAt);
        if ($startTs === false || $endTs === false) {
            $endTs = time();
            $startTs = $endTs;
        }

        $minutes = max(0, (int) floor(($endTs - $startTs) / 60));
        $bonus = (int) (floor($minutes / self::MINUTES_PER_BLOCK) * self::COINS_PER_BLOCK);

        $this->ended_at = $endedAt;
        $this->duration_minutes = $minutes;
        $this->bonus_coins = $awardBonus ? $bonus : 0;
        $this->status = self::STATUS_ENDED;
        $this->updated_at = $endedAt;
        $this->save(false);

        if ($awardBonus && $bonus > 0) {
            $user = User::findOne($this->user_id);
            if ($user === null) {
                return;
            }
            $balance = $user->getPersonalBalance();
            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_MEDIA_LIVE;
            $profit->amount = (string) $bonus;
            $profit->user_balance_id = $balance->id;
            $profit->comment = Yii::t('common', 'Бонус за эфир ({platform}), #{id}', [
                'platform' => $this->platform,
                'id' => $this->id,
            ], 'ru-RU');
            $profit->created_at = $endedAt;
            $profit->save(false);
            $balance->recalculateBalance();
        }
    }

    /**
     * Закрыть лишние активные строки (защита от гонок), оставив одну с максимальным id.
     *
     * @return self|null Оставшаяся активная запись или null
     */
    public static function ensureSingleActiveSession(int $userId, string $now): ?self
    {
        /** @var self[] $rows */
        $rows = static::find()
            ->where(['user_id' => $userId, 'status' => self::STATUS_LIVE])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        if ($rows === []) {
            return null;
        }

        if (count($rows) === 1) {
            return $rows[0];
        }

        $keep = array_pop($rows);
        foreach ($rows as $dup) {
            $dup->finalize($now, false);
        }

        return $keep;
    }
}
