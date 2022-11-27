<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use common\models\news\NewsContent;
use Yii;

/**
 * @property int                 $id
 * @property int                 $drop_id
 * @property int                 $type
 * @property string              $image
 * @property string              $created_at
 */
class DropImage extends ActiveRecord
{
    const TYPE_ORIG = 1;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'drop_image';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'drop_id'               => Yii::t('common', 'Дроп'),
            'type'               => Yii::t('common', 'Тип'),
            'image'               => Yii::t('common', 'Изображение'),
            'created_at'          => Yii::t('common', 'Дата создания'),
        ];
    }

    public function getImagePubUrl() {
        return "/uploads" . $this->image;
    }

    public function rules(): array
    {
        return [
            [['drop_id', 'type', 'image'], 'required'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * @throws \Exception
     */
    public static function createRecord($image, $type, $dropId): bool
    {
        $model = new self();
        $model->image = $image;
        $model->type = $type;
        $model->drop_id = $dropId;
        $model->created_at = date('Y-m-d H:i:s');
        try {
            $model->save(false);
        } catch (\Exception $e) {
            \Yii::info("Drop Image file string not save " . print_r($e->getMessage(), 1), 'problem');
            return false;
        }
        return true;
    }

}
