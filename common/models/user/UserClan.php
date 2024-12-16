<?php

namespace common\models\user;

use common\models\clan\Clan;
use Yii;

/**
 * This is the model class for table "user_clan".
 *
 * @property int $id
 * @property int $user_id
 * @property int $clan_id
 * @property int $invited_user_id
 * @property int $status
 * @property string|null $created_at
 *
 * @property Clan $clan
 * @property User $invitedUser
 * @property User $user
 */
class UserClan extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_clan';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'clan_id', 'invited_user_id', 'status'], 'required'],
            [['user_id', 'clan_id', 'invited_user_id', 'status'], 'integer'],
            [['created_at'], 'safe'],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
            [['invited_user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['invited_user_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'clan_id' => 'Clan ID',
            'invited_user_id' => 'Invited User ID',
            'status' => 'Status',
            'created_at' => 'Created At',
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
     * Gets query for [[InvitedUser]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getInvitedUser()
    {
        return $this->hasOne(User::class, ['id' => 'invited_user_id']);
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
}
