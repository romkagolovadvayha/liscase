<?php

namespace common\models\radio;

use common\models\user\User;
use Yii;

/**
 * This is the model class for table "radio_track_like".
 *
 * @property int $id
 * @property int $radio_track_id
 * @property int $user_id
 * @property string|null $created_at
 *
 * @property RadioTrack $radioTrack
 * @property User $user
 */
class RadioTrackLike extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'radio_track_like';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['radio_track_id', 'user_id'], 'required'],
            [['radio_track_id', 'user_id'], 'integer'],
            [['created_at'], 'safe'],
            [['radio_track_id', 'user_id'], 'unique', 'targetAttribute' => ['radio_track_id', 'user_id']],
            [['radio_track_id'], 'exist', 'skipOnError' => true, 'targetClass' => RadioTrack::class, 'targetAttribute' => ['radio_track_id' => 'id']],
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
            'radio_track_id' => Yii::t('common', 'Трек'),
            'user_id' => 'User ID',
            'created_at' => Yii::t('common', 'Дата'),
        ];
    }

    /**
     * Gets query for [[RadioTrack]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRadioTrack()
    {
        return $this->hasOne(RadioTrack::class, ['id' => 'radio_track_id']);
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

