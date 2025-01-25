<?php
namespace common\models\template;

use Yii;
use yii\db\ActiveRecord;

class Template extends ActiveRecord
{
    public static function tableName()
    {
        return 'template';
    }

    public function rules()
    {
        return [
            [['name'], 'required'],
            ['name', 'string', 'max' => 255],
        ];
    }
}