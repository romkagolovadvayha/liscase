<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use common\models\user\User;
use Yii;

/**
 * Публичная ссылка-приглашение в клан (как в Discord).
 *
 * @property int $id
 * @property int $clan_id
 * @property string $token
 * @property int $inviter_user_id
 * @property string|null $expires_at
 * @property int $max_uses
 * @property int $uses_count
 * @property int $created_at
 *
 * @property Clan $clan
 * @property User $inviterUser
 */
class ClanInviteLink extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'clan_invite_links';
    }

    public function rules(): array
    {
        return [
            [['clan_id', 'inviter_user_id', 'created_at'], 'required'],
            [['clan_id', 'inviter_user_id', 'max_uses', 'uses_count', 'created_at'], 'integer'],
            [['expires_at'], 'safe'],
            [['token'], 'string', 'max' => 64],
            [['token'], 'unique'],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
            [['inviter_user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['inviter_user_id' => 'id']],
        ];
    }

    public function getClan(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Clan::class, ['id' => 'clan_id']);
    }

    public function getInviterUser(): \yii\db\ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'inviter_user_id']);
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null || $this->expires_at === '') {
            return false;
        }

        return strtotime($this->expires_at) < time();
    }

    public function isUseLimitReached(): bool
    {
        if ($this->max_uses <= 0) {
            return false;
        }

        return $this->uses_count >= $this->max_uses;
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
