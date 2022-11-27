<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use common\models\news\NewsContent;
use Yii;

/**
 * @property int                 $id
 * @property int                 $box_id
 * @property int                 $type
 * @property string              $image
 * @property string              $created_at
 */
class BoxImage extends ActiveRecord
{
    const TYPE_ORIG = 1;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'box_image';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'box_id'               => Yii::t('common', 'box'),
            'type'               => Yii::t('common', 'Тип'),
            'image'               => Yii::t('common', 'Изображение'),
            'created_at'          => Yii::t('common', 'Дата создания'),
        ];
    }

    public function rules(): array
    {
        return [
            [['drop_id', 'type', 'image'], 'required'],
            [['created_at'], 'safe'],
        ];
    }

    public function getImagePubUrl() {
        return "/uploads" . $this->image;
    }

    /**
     * @throws \Exception
     */
    public static function createRecord($image, $type, $boxId): bool
    {
        /** @var BoxImage $model */
        $model = BoxImage::find()
            ->andWhere(['type' => $type])
            ->andWhere(['box_id' => $boxId])
            ->one();

        if (empty($model)) {
            $model = new BoxImage();
            $model->type = $type;
            $model->box_id = $boxId;
            $model->created_at = date('Y-m-d H:i:s');
        } else {
            try {
                $uploadDir = Yii::getAlias('@app/web/uploads');
                unlink($uploadDir . $model->image);
            } catch (\Exception $ex) {}
        }
        $model->image = $image;
        try {
            $model->save(false);
        } catch (\Exception $e) {
            \Yii::info("Box Image file string not save " . print_r($e->getMessage(), 1), 'problem');
            return false;
        }
        return true;
    }

}
