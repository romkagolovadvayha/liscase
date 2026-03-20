<?php

namespace common\models\clan;

use common\components\base\ActiveRecord;
use common\models\user\User;
use Yii;

/**
 * This is the model class for table "clan_invites".
 *
 * @property int $id
 * @property int $clan_id
 * @property int $inviter_user_id
 * @property int $invited_user_id
 * @property string $status
 * @property string|null $expires_at
 * @property int $created_at
 *
 * @property Clan $clan
 * @property User $inviterUser
 * @property User $invitedUser
 */
class ClanInvite extends ActiveRecord
{
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_DECLINED = 'declined';
    const STATUS_EXPIRED = 'expired';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clan_invites';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['clan_id', 'inviter_user_id', 'invited_user_id'], 'required'],
            [['clan_id', 'inviter_user_id', 'invited_user_id'], 'integer'],
            [['expires_at'], 'safe'],
            [['status'], 'in', 'range' => [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_DECLINED, self::STATUS_EXPIRED]],
            [['status'], 'default', 'value' => self::STATUS_PENDING],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
            [['inviter_user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['inviter_user_id' => 'id']],
            [['invited_user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['invited_user_id' => 'id']],
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
            'inviter_user_id' => Yii::t('common', 'Пригласивший'),
            'invited_user_id' => Yii::t('common', 'Приглашенный'),
            'status' => Yii::t('common', 'Статус'),
            'expires_at' => Yii::t('common', 'Истекает'),
            'created_at' => Yii::t('common', 'Дата создания'),
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
     * Gets query for [[InviterUser]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInviterUser()
    {
        return $this->hasOne(User::class, ['id' => 'inviter_user_id']);
    }

    /**
     * Gets query for [[InvitedUser]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInvitedUser()
    {
        return $this->hasOne(User::class, ['id' => 'invited_user_id']);
    }

    /**
     * Проверка истечения приглашения
     *
     * @return bool
     */
    public function isExpired()
    {
        if ($this->expires_at === null) {
            return false;
        }

        return strtotime($this->expires_at) < time();
    }

    /**
     * Принятие приглашения
     *
     * @return bool
     */
    public function accept()
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        if ($this->isExpired()) {
            $this->status = self::STATUS_EXPIRED;
            $this->save(false);
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // Добавление участника в клан
            $member = $this->clan->addMember($this->invited_user_id);
            if (!$member) {
                throw new \Exception('Не удалось добавить участника в клан');
            }

            // Обновление статуса приглашения
            $this->status = self::STATUS_ACCEPTED;
            $this->save(false);

            $transaction->commit();
            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            return false;
        }
    }

    /**
     * Отклонение приглашения
     *
     * @return bool
     */
    public function decline()
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->status = self::STATUS_DECLINED;
        return $this->save(false);
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            // Установка даты истечения при создании (7 дней по умолчанию)
            if ($insert && $this->expires_at === null) {
                $this->expires_at = date('Y-m-d H:i:s', time() + 7 * 24 * 60 * 60);
            }
            return true;
        }
        return false;
    }
}

