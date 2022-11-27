<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use Yii;

/**
 * @property int                 $id
 * @property string              $name
 * @property string              $type
 */
class DropType extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'drop_type';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'name'               => Yii::t('common', 'Название'),
            'type'              => Yii::t('common', 'Тип'),
        ];
    }

    public function rules(): array
    {
        return [
            [['name', 'type'], 'required'],
            [['name', 'type'], 'string', 'max' => 255],
        ];
    }

    /**
     * @throws \Exception
     */
    public static function createRecord($name, $type)
    {
        $dropType = DropType::find()
            ->andWhere(['name' => $name])
            ->andWhere(['type' => $type])
            ->one();

        if (!empty($dropType)) {
            return $dropType->id;
        }

        $dropType = new DropType();
        $dropType->name = $name;
        $dropType->type = $type;
        $dropType->save(false);
        return Yii::$app->db->getLastInsertID();
    }

    public static function getTypeList() {
        $types = DropType::find()->all();
        $list = [];
        foreach ($types as $item) {
            $list[$item->id] = $item->name;
        }
        return $list;
    }

}
