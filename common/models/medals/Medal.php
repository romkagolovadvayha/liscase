<?php

namespace common\models\medals;

use common\components\base\ActiveRecord;
use Yii;

/**
 * @property int $id
 * @property string|null $code
 * @property string $name
 * @property string|null $description
 * @property string|null $image_path
 * @property int $is_active
 * @property string $created_at
 * @property string $updated_at
 */
class Medal extends ActiveRecord
{
    /** @var \yii\web\UploadedFile|null */
    public $imageFile;

    public static function tableName()
    {
        return 'medal';
    }

    public function rules()
    {
        return [
            [['name'], 'required'],
            [['description'], 'string'],
            [['is_active'], 'boolean'],
            [['code'], 'string', 'max' => 64],
            [['code'], 'unique'],
            [['name'], 'string', 'max' => 255],
            [['image_path'], 'string', 'max' => 512],
            [['imageFile'], 'file', 'skipOnEmpty' => true, 'extensions' => ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], 'maxSize' => 5 * 1024 * 1024],
        ];
    }

    public function attributeLabels()
    {
        return [
            'name' => Yii::t('common', 'Название'),
            'description' => Yii::t('common', 'Описание'),
            'image_path' => Yii::t('common', 'Изображение'),
            'imageFile' => Yii::t('common', 'Изображение'),
            'is_active' => Yii::t('common', 'Активна'),
        ];
    }

    public function getUserMedals()
    {
        return $this->hasMany(UserMedal::class, ['medal_id' => 'id']);
    }

    public function getImageUrl(): string
    {
        if (!$this->image_path) {
            return '/images/awards/award1.png';
        }
        if (preg_match('~^https?://~i', $this->image_path) || strpos($this->image_path, '/') === 0) {
            return $this->image_path;
        }

        return rtrim((string)Yii::$app->settings->get('s3_publicUrl'), '/') . '/' . ltrim($this->image_path, '/');
    }
}
