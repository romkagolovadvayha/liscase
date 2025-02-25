<?php

namespace common\models\clan;

use common\models\user\User;
use Yii;

/**
 * This is the model class for table "clan_question".
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $clan_id
 * @property string|null $description
 * @property string|null $social_youtube
 * @property string|null $social_discord
 * @property string|null $social_vk
 * @property string|null $social_twitch
 * @property int $status
 * @property string|null $created_at
 *
 * @property Clan $clan
 * @property User $user
 */
class ClanQuestion extends \yii\db\ActiveRecord
{

    const STATUS_WAIT = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_REJECT = 2;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'clan_question';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'clan_id', 'status'], 'integer'],
            [['created_at'], 'safe'],
            [['description'], 'string', 'min' => 50],
            [['description'], 'required'],
            [['description', 'social_youtube', 'social_discord', 'social_vk', 'social_twitch'], 'string', 'max' => 255],
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
            'description' => Yii::t('common', 'Информация о себе'),
            'social_youtube' => Yii::t('common', 'Ссылка на Youtube'),
            'social_discord' => Yii::t('common', 'Ваш логин Discord'),
            'social_vk' => Yii::t('common', 'Ссылка на профиль VK'),
            'social_twitch' => Yii::t('common', 'Ссылка на Twitch'),
            'status' => Yii::t('common', 'Статус'),
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
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
