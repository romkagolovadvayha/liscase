<?php

namespace common\models\clan;

use common\models\user\User;
use Yii;

/**
 * This is the model class for table "clan".
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $description_short
 * @property string|null $description
 * @property string|null $logo_image
 * @property string|null $background_image
 * @property int|null $user_id
 * @property string|null $social_youtube
 * @property string|null $social_discord
 * @property string|null $social_vk
 * @property string|null $social_twitch
 * @property string|null $created_at
 *
 * @property ClanInvite[] $clanInvites
 * @property ClanPage[] $clanPages
 * @property ClanQuestion[] $clanQuestions
 * @property ClanResource[] $clanResources
 * @property User $user
 * @property UserClan[] $userClans
 * @property UserRole[] $userRoles
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
            [['description'], 'string'],
            [['user_id'], 'integer'],
            [['created_at'], 'safe'],
            [['title', 'logo_image', 'background_image', 'social_youtube', 'social_discord', 'social_vk', 'social_twitch'], 'string', 'max' => 255],
            [['description_short'], 'string', 'max' => 110],
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
            'title' => 'Title',
            'description_short' => 'Description Short',
            'description' => 'Description',
            'logo_image' => 'Logo Image',
            'background_image' => 'Background Image',
            'user_id' => 'User ID',
            'social_youtube' => 'Social Youtube',
            'social_discord' => 'Social Discord',
            'social_vk' => 'Social Vk',
            'social_twitch' => 'Social Twitch',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[ClanInvites]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClanInvites()
    {
        return $this->hasMany(ClanInvite::class, ['clan_id' => 'id']);
    }

    /**
     * Gets query for [[ClanPages]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClanPages()
    {
        return $this->hasMany(ClanPage::class, ['clan_id' => 'id']);
    }

    /**
     * Gets query for [[ClanQuestions]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClanQuestions()
    {
        return $this->hasMany(ClanQuestion::class, ['clan_id' => 'id']);
    }

    /**
     * Gets query for [[ClanResources]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getClanResources()
    {
        return $this->hasMany(ClanResource::class, ['clan_id' => 'id']);
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

    /**
     * Gets query for [[UserRoles]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUserRoles()
    {
        return $this->hasMany(UserRole::class, ['clan_id' => 'id']);
    }
}
