<?php

namespace common\models\video;

use common\models\user\User;
use Yii;

/**
 * Видео пользователей (модерация как server_skin).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $type
 * @property string $video_link
 * @property string|null $poster_image
 * @property string|null $poster_image_150
 * @property string|null $poster_image_400
 * @property int $status
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property int $likes
 *
 * @property User $user
 * @property UserVideoLike[] $videoLikes
 */
class UserVideo extends \yii\db\ActiveRecord
{
    const STATUS_ACTIVE = 1;
    const STATUS_REJECT = 2;
    const STATUS_WAIT = 3;

    const TYPE_YOUTUBE = 'youtube';
    const TYPE_TIKTOK = 'tiktok';
    const TYPE_OTHER = 'other';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_video';
    }

    public static function getStatusList(): array
    {
        return [
            self::STATUS_REJECT => Yii::t('common', 'Отклонен'),
            self::STATUS_ACTIVE  => Yii::t('common', 'Активен'),
            self::STATUS_WAIT   => Yii::t('common', 'На модерации'),
        ];
    }

    public static function getTypeList(): array
    {
        return [
            self::TYPE_YOUTUBE => 'YouTube',
            self::TYPE_TIKTOK => 'TikTok',
            self::TYPE_OTHER  => Yii::t('common', 'Другое'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'name', 'type', 'video_link', 'status'], 'required'],
            [['user_id', 'status', 'likes'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['name'], 'string', 'max' => 255],
            [['video_link', 'poster_image', 'poster_image_150', 'poster_image_400'], 'string', 'max' => 500],
            [['type'], 'string', 'max' => 32],
            [['type'], 'in', 'range' => array_keys(self::getTypeList())],
            [['status'], 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_REJECT, self::STATUS_WAIT]],
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
            'user_id' => Yii::t('common', 'Пользователь'),
            'name' => Yii::t('common', 'Название'),
            'type' => Yii::t('common', 'Тип'),
            'video_link' => Yii::t('common', 'Ссылка на видео'),
            'poster_image' => Yii::t('common', 'Постер'),
            'poster_image_150' => Yii::t('common', 'Постер 150'),
            'poster_image_400' => Yii::t('common', 'Постер 400'),
            'status' => Yii::t('common', 'Статус'),
            'created_at' => Yii::t('common', 'Дата создания'),
            'updated_at' => Yii::t('common', 'Дата обновления'),
        ];
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $this->created_at = $this->created_at ?: date('Y-m-d H:i:s');
            }
            $this->updated_at = date('Y-m-d H:i:s');
            return true;
        }
        return false;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getVideoLikes()
    {
        return $this->hasMany(UserVideoLike::class, ['user_video_id' => 'id']);
    }
}
