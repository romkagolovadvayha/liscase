<?php

namespace common\models\building;

use Yii;

/**
 * This is the model class for table "building_image".
 *
 * @property int $id
 * @property int $building_id
 * @property string $image
 * @property string|null $created_at
 *
 * @property Building $building
 */
class BuildingImage extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'building_image';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['building_id', 'image'], 'required'],
            [['building_id'], 'integer'],
            [['image'], 'string'],
            [['created_at'], 'safe'],
            [['building_id'], 'exist', 'skipOnError' => true, 'targetClass' => Building::class, 'targetAttribute' => ['building_id' => 'id']],
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
            'image' => 'Image',
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

    public function getPublicUrl() {
        return Yii::$app->params['baseUrl'] . '/uploads/buildings/' . $this->image;
    }

    public function getPublicUrlPreview() {
        return Yii::$app->params['baseUrl'] . '/uploads/buildings/preview_' . $this->image;
    }
}
