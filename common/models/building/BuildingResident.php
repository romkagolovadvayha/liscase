<?php

namespace common\models\building;

use Yii;
use common\models\user\User;

/**
 * This is the model class for table "building_resident".
 *
 * @property int $id
 * @property int $building_id
 * @property int $user_id
 *
 * @property Building $building
 * @property User $user
 */
class BuildingResident extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'building_resident';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['building_id', 'user_id'], 'required'],
            [['building_id', 'user_id'], 'integer'],
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
