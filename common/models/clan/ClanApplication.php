<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use common\models\user\User;
use Yii;

/**
 * Заявка на вступление в клан.
 *
 * @property int $id
 * @property int $clan_id
 * @property int $user_id
 * @property string|null $message
 * @property string $status
 * @property int $created_at
 * @property int|null $resolved_at
 * @property int|null $resolved_by_user_id
 *
 * @property Clan $clan
 * @property User $user
 * @property User|null $resolvedByUser
 */
class ClanApplication extends ActiveRecord
{
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';

    public static function tableName(): string
    {
        return 'clan_applications';
    }

    public function rules(): array
    {
        return [
            [['clan_id', 'user_id', 'created_at'], 'required'],
            [['clan_id', 'user_id', 'created_at', 'resolved_at', 'resolved_by_user_id'], 'integer'],
            [['message'], 'string'],
            [['status'], 'in', 'range' => [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_REJECTED]],
            [['status'], 'default', 'value' => self::STATUS_PENDING],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    public function getClan(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Clan::class, ['id' => 'clan_id']);
    }

    public function getUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getResolvedByUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'resolved_by_user_id']);
    }
}
