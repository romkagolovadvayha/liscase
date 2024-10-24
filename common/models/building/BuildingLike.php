<?php

namespace common\models\building;

use common\models\user\User;
use Yii;

/**
 * This is the model class for table "building_like".
 *
 * @property int $id
 * @property int $building_id
 * @property int $user_id
 * @property int $type
 * @property string|null $created_at
 *
 * @property Building $building
 * @property User $user
 */
class BuildingLike extends \yii\db\ActiveRecord
{
    public const TYPE_LIKE = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'building_like';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['building_id', 'user_id', 'type'], 'required'],
            [['building_id', 'user_id', 'type'], 'integer'],
            [['created_at'], 'safe'],
            [['building_id'], 'exist', 'skipOnError' => true, 'targetClass' => Building::class, 'targetAttribute' => ['building_id' => 'id']],
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
            'building_id' => 'Building ID',
            'user_id' => 'User ID',
            'type' => 'Type',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[Building]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBuilding()
    {
        return $this->hasOne(Building::class, ['id' => 'building_id']);
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
