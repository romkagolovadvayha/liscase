<?php

namespace common\models\clan;

use common\models\user\User;
use Yii;

/**
 * This is the model class for table "clan_invite".
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $clan_id
 * @property string|null $hash
 * @property string|null $created_at
 *
 * @property Clan $clan
 * @property User $user
 * @property UserClan[] $userClans
 */
class ClanInvite extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clan_invite';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'clan_id'], 'integer'],
            [['created_at'], 'safe'],
            [['hash'], 'string', 'max' => 255],
            [['clan_id'], 'exist', 'skipOnError' => true, 'targetClass' => Clan::class, 'targetAttribute' => ['clan_id' => 'id']],
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
            'hash' => 'Hash',
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
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Gets query for [[UserClans]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUserClans()
    {
        return $this->hasMany(UserClan::class, ['clan_invite_id' => 'id']);
    }
}
