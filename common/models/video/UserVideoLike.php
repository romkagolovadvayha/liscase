<?php

namespace common\models\video;

use common\models\user\User;
use Yii;

/**
 * Лайк пользователя на видео.
 *
 * @property int $id
 * @property int $user_video_id
 * @property int $user_id
 * @property int $type
 * @property string|null $created_at
 *
 * @property UserVideo $userVideo
 * @property User $user
 */
class UserVideoLike extends \yii\db\ActiveRecord
{
    public const TYPE_LIKE = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_video_like';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_video_id', 'user_id', 'type'], 'required'],
            [['user_video_id', 'user_id', 'type'], 'integer'],
            [['created_at'], 'safe'],
            [['user_video_id'], 'exist', 'skipOnError' => true, 'targetClass' => UserVideo::class, 'targetAttribute' => ['user_video_id' => 'id']],
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
            'user_video_id' => 'User Video ID',
            'user_id' => 'User ID',
            'type' => 'Type',
            'created_at' => 'Created At',
        ];
    }

    public function getUserVideo()
    {
        return $this->hasOne(UserVideo::class, ['id' => 'user_video_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
