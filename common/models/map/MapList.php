<?php

namespace common\models\map;

use Yii;

/**
 * This is the model class for table "map_list".
 *
 * @property int $id
 * @property string|null $hash
 * @property string|null $url
 * @property string|null $image
 * @property string|null $image_preview
 * @property string|null $size
 * @property string|null $created_at
 *
 * @property Map[] $maps
 */
class MapList extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'map_list';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['created_at'], 'safe'],
            [['hash', 'url', 'image', 'size'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'hash' => 'Hash',
            'url' => 'Url',
            'image' => 'Image',
            'size' => 'Size',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Gets query for [[Maps]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getMaps()
    {
        return $this->hasMany(Map::class, ['map_list_id' => 'id']);
    }
}
