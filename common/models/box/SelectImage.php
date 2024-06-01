<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use common\models\news\NewsContent;
use Yii;

/**
 * @property int                 $id
 * @property int                 $select_id
 * @property int                 $type
 * @property string              $image
 * @property string              $created_at
 */
class SelectImage extends ActiveRecord
{
    const TYPE_ORIG = 1;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'select_image';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'select_id'               => Yii::t('common', 'Select'),
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
    public static function createRecord($image, $type, $SelectId): bool
    {
        $models = self::find()
                      ->andWhere(['select_id' => $SelectId])
                      ->andWhere(['type' => $type])
                      ->all();
        if (!empty($models)) {
            foreach ($models as $model) {
                $model->delete();
            }
        }
        /** @var SelectImage $model */
        $model = SelectImage::find()
            ->andWhere(['type' => $type])
            ->andWhere(['select_id' => $SelectId])
            ->one();

        if (empty($model)) {
            $model = new SelectImage();
            $model->type = $type;
            $model->select_id = $SelectId;
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
            \Yii::info("Select Image file string not save " . print_r($e->getMessage(), 1), 'problem');
            return false;
        }
        return true;
    }

}
