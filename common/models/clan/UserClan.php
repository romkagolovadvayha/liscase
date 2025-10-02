<?php

namespace common\models\clan;

use common\models\user\User;
use Yii;

/**
 * This is the model class for table "user_clan".
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $steam_id
 * @property int|null $clan_id
 * @property int|null $clan_invite_id
 * @property bool|null $status
 * @property string|null $leave_at
 * @property string|null $created_at
 *
 * @property Clan $clan
 * @property ClanInvite $clanInvite
 * @property User $user
 * @property UserRole[] $userRoles
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
            [['user_id', 'clan_id', 'clan_invite_id'], 'integer'],
            [['created_at'], 'safe'],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
            [['clan_invite_id'], 'exist', 'skipOnError' => true, 'targetClass' => ClanInvite::class, 'targetAttribute' => ['clan_invite_id' => 'id']],
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
            'clan_invite_id' => 'Clan Invite ID',
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
     * Gets query for [[ClanInvite]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClanInvite()
    {
        return $this->hasOne(ClanInvite::class, ['id' => 'clan_invite_id']);
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
     * Gets query for [[UserRoles]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUserRoles()
    {
        return $this->hasMany(UserRole::class, ['user_id' => 'user_id'])
            ->andWhere(['clan_id' => $this->clan_id]);
    }
}
