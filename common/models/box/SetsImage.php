<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use Yii;

/**
 * @property int                 $id
 * @property int                 $sets_id
 * @property int                 $type
 * @property string              $image
 * @property string              $created_at
 */
class SetsImage extends ActiveRecord
{
    const TYPE_ORIG = 1;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'sets_image';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'sets_id'               => Yii::t('common', 'Сет'),
            'type'               => Yii::t('common', 'Тип'),
            'image'               => Yii::t('common', 'Изображение'),
            'created_at'          => Yii::t('common', 'Дата создания'),
        ];
    }

    public function rules(): array
    {
        return [
            [['sets_id', 'type', 'image'], 'required'],
            [['created_at'], 'safe'],
        ];
    }

    public function getImagePubUrl() {
        return "/uploads" . $this->image;
    }

    /**
     * @throws \Exception
     */
    public static function createRecord($image, $type, $setsId): bool
    {
        $models = self::find()
                      ->andWhere(['sets_id' => $setsId])
                      ->andWhere(['type' => $type])
                      ->all();
        if (!empty($models)) {
            foreach ($models as $model) {
                $model->delete();
            }
        }
        /** @var BoxImage $model */
        $model = SetsImage::find()
            ->andWhere(['type' => $type])
            ->andWhere(['sets_id' => $setsId])
            ->one();

        if (empty($model)) {
            $model = new SetsImage();
            $model->type = $type;
            $model->sets_id = $setsId;
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
            \Yii::info("Sets Image file string not save " . print_r($e->getMessage(), 1), 'problem');
            return false;
        }
        return true;
    }

}
