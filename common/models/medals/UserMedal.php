<?php

namespace common\models\medals;

use common\components\base\ActiveRecord;
use common\models\user\User;

/**
 * @property int $id
 * @property int $user_id
 * @property int $medal_id
 * @property string $source_type
 * @property int|null $source_id
 * @property string|null $note
 * @property int|null $awarded_by_user_id
 * @property string $awarded_at
 * @property string $created_at
 *
 * @property Medal $medal
 * @property User $user
 */
class UserMedal extends ActiveRecord
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_BATTLE_PASS = 'battle_pass';
    public const SOURCE_ANNUAL_PLAYTIME = 'annual_playtime';
    public const SOURCE_ANNUAL_SERVER_RECORD = 'annual_server_record';
    public const SOURCE_CASH_RACE = 'cash_race';

    public static function tableName()
    {
        return 'user_medal';
    }

    public function rules()
    {
        return [
            [['user_id', 'medal_id'], 'required'],
            [['user_id', 'medal_id', 'source_id', 'awarded_by_user_id'], 'integer'],
            [['note'], 'string'],
            [['source_type'], 'string', 'max' => 32],
            [['awarded_at'], 'safe'],
            [['user_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['medal_id'], 'exist', 'targetClass' => Medal::class, 'targetAttribute' => ['medal_id' => 'id']],
        ];
    }

    public function getMedal()
    {
        return $this->hasOne(Medal::class, ['id' => 'medal_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public static function award(int $userId, int $medalId, string $sourceType = self::SOURCE_MANUAL, ?int $sourceId = null, ?string $note = null, ?int $awardedByUserId = null): self
    {
        $model = static::findOne(['user_id' => $userId, 'medal_id' => $medalId]);
        if ($model) {
            return $model;
        }

        $model = new static();
        $model->user_id = $userId;
        $model->medal_id = $medalId;
        $model->source_type = $sourceType;
        $model->source_id = $sourceId;
        $model->note = $note;
        $model->awarded_by_user_id = $awardedByUserId;
        $model->awarded_at = date('Y-m-d H:i:s');
        try {
            if (!$model->save()) {
                throw new \RuntimeException('Не удалось начислить медаль: ' . json_encode($model->errors, JSON_UNESCAPED_UNICODE));
            }
        } catch (\yii\db\IntegrityException $e) {
            $existing = static::findOne(['user_id' => $userId, 'medal_id' => $medalId]);
            if ($existing) {
                return $existing;
            }
            throw $e;
        }

        return $model;
    }

    public static function getUserAwardsPayload(int $userId, ?int $limit = null): array
    {
        $query = static::find()
            ->alias('um')
            ->innerJoin(['m' => Medal::tableName()], 'm.id = um.medal_id')
            ->with('medal')
            ->where(['um.user_id' => $userId, 'm.is_active' => 1])
            ->orderBy(['um.awarded_at' => SORT_DESC, 'um.id' => SORT_DESC]);
        if ($limit !== null) {
            $query->limit($limit);
        }

        $result = [];
        foreach ($query->all() as $userMedal) {
            $medal = $userMedal->medal;
            $result[] = [
                'id' => (int)$medal->id,
                'name' => $medal->name,
                'description' => $medal->description ?: $userMedal->note,
                'note' => $userMedal->note,
                'image' => $medal->getImageUrl(),
                'completed' => true,
                'source' => $userMedal->source_type,
                'awardedAt' => $userMedal->awarded_at,
            ];
        }
        return $result;
    }

    public static function countUserActiveMedals(int $userId): int
    {
        return (int)static::find()
            ->alias('um')
            ->innerJoin(['m' => Medal::tableName()], 'm.id = um.medal_id')
            ->where(['um.user_id' => $userId, 'm.is_active' => 1])
            ->count();
    }
}
