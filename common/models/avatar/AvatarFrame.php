<?php

namespace common\models\avatar;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property string $image_key
 * @property int $is_active
 * @property int $sort
 * @property int $created_at
 * @property int $updated_at
 */
class AvatarFrame extends ActiveRecord
{
    public static function tableName()
    {
        return 'avatar_frame';
    }

    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
            ],
        ];
    }

    public function rules()
    {
        return [
            [['name', 'image_key'], 'required'],
            [['is_active', 'sort', 'created_at', 'updated_at'], 'integer'],
            [['name'], 'string', 'max' => 255],
            [['image_key'], 'string', 'max' => 512],
            [['is_active'], 'in', 'range' => [0, 1]],
            [['sort'], 'default', 'value' => 100],
            [['is_active'], 'default', 'value' => 1],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Название',
            'image_key' => 'Ключ изображения (S3)',
            'is_active' => 'Активна',
            'sort' => 'Сортировка',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
        ];
    }

    public function getImageUrl(): ?string
    {
        if (empty($this->image_key)) {
            return null;
        }
        if (Yii::$app->has('s3Api')) {
            return Yii::$app->s3Api->getPublicUrl($this->image_key);
        }

        return null;
    }

    /**
     * @return static[]
     */
    public static function getActiveOrdered(): array
    {
        return static::find()
            ->where(['is_active' => 1])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }
}

