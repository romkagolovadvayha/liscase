<?php

namespace common\models\clan;

use common\models\user\User;
use common\models\user\UserClan;
use Yii;

/**
 * This is the model class for table "clan".
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $description
 * @property string|null $discord
 * @property string|null $vk
 * @property string|null $telegram
 * @property int $recruitment
 * @property int $user_id
 * @property string|null $created_at
 *
 * @property ClanApplication[] $clanApplications
 * @property User $user
 * @property UserClan[] $userClans
 */
class Clan extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clan';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['recruitment', 'user_id'], 'required'],
            [['recruitment', 'user_id'], 'integer'],
            [['created_at'], 'safe'],
            [['name', 'discord', 'vk', 'telegram'], 'string', 'max' => 255],
            [['description'], 'string', 'max' => 500],
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
            'name' => 'Name',
            'description' => 'Description',
            'discord' => 'Discord',
            'vk' => 'Vk',
            'telegram' => 'Telegram',
            'recruitment' => 'Recruitment',
            'user_id' => 'User ID',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[ClanApplications]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClanApplications()
    {
        return $this->hasMany(ClanApplication::class, ['clan_id' => 'id']);
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
        return $this->hasMany(UserClan::class, ['clan_id' => 'id']);
    }
}
