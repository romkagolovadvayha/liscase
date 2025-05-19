<?php

namespace common\models\mirrors;

use Yii;

/**
 * This is the model class for table "mirrors".
 *
 * @property int $id
 * @property string $steam_id
 * @property string|null $mirror_name
 * @property string|null $created_at
 */
class Mirrors extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'mirrors';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['steam_id'], 'required'],
            [['created_at'], 'safe'],
            [['steam_id'], 'string', 'max' => 19],
            [['mirror_name'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'steam_id' => 'Steam ID',
            'mirror_name' => 'Mirror Name',
            'created_at' => 'Created At',
        ];
    }
}
