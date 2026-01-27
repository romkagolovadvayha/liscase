<?php

namespace common\models\support;

use Yii;

/**
 * This is the model class for table "support_sticker".
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $file
 * @property string $type
 * @property int|null $width
 * @property int|null $height
 * @property int $sort
 * @property int $status
 * @property int $created_at
 * @property int $updated_at
 */
class SupportSticker extends \yii\db\ActiveRecord
{
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'support_sticker';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['code', 'name'], 'required'],
            [['width', 'height', 'sort', 'status', 'created_at', 'updated_at'], 'integer'],
            [['code'], 'string', 'max' => 50],
            [['name'], 'string', 'max' => 255],
            [['file'], 'string', 'max' => 512],
            [['type'], 'string', 'max' => 20],
            [['code'], 'unique'],
            [['type'], 'in', 'range' => [self::TYPE_IMAGE, self::TYPE_VIDEO]],
            [['status'], 'in', 'range' => [self::STATUS_INACTIVE, self::STATUS_ACTIVE]],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'code' => 'Код',
            'name' => 'Название',
            'file' => 'Файл',
            'type' => 'Тип',
            'width' => 'Ширина',
            'height' => 'Высота',
            'sort' => 'Сортировка',
            'status' => 'Статус',
            'created_at' => 'Дата создания',
            'updated_at' => 'Дата обновления',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => \yii\behaviors\TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
            ],
        ];
    }

    /**
     * Получает публичный URL для стикера в S3
     * 
     * @return string Публичный URL
     */
    public function getPublicUrl()
    {
        $s3Api = Yii::$app->s3Api;
        $s3Key = 'support/stickers/' . $this->file;
        return $s3Api->getPublicUrl($s3Key);
    }

    /**
     * Получает HTML-тег для стикера
     * 
     * @return string HTML-тег
     */
    public function getHtmlTag()
    {
        if ($this->type === self::TYPE_IMAGE) {
            $width = $this->width ? " width=\"{$this->width}\"" : '';
            $height = $this->height ? " height=\"{$this->height}\"" : '';
            return "<img src=\"{$this->getPublicUrl()}\" class=\"support_sticker\"{$width}{$height} alt=\"{$this->name}\" />";
        } else {
            $width = $this->width ? " width=\"{$this->width}\"" : '';
            $height = $this->height ? " height=\"{$this->height}\"" : '';
            return "<video src=\"{$this->getPublicUrl()}\" class=\"support_sticker\"{$width}{$height} autoplay loop muted></video>";
        }
    }

    /**
     * Получает список статусов
     * 
     * @return array
     */
    public static function getStatusList()
    {
        $cacheKey = 'support_sticker_status_list';
        $cached = Yii::$app->cache->get($cacheKey);
        
        if ($cached === false) {
            $cached = [
                self::STATUS_INACTIVE => Yii::t('common', 'Неактивен'),
                self::STATUS_ACTIVE => Yii::t('common', 'Активен'),
            ];
            // Кэшируем на 24 часа (86400 секунд)
            Yii::$app->cache->set($cacheKey, $cached, 86400);
        }
        
        return $cached;
    }

    /**
     * Получает список типов
     * 
     * @return array
     */
    public static function getTypeList()
    {
        $cacheKey = 'support_sticker_type_list';
        $cached = Yii::$app->cache->get($cacheKey);
        
        if ($cached === false) {
            $cached = [
                self::TYPE_IMAGE => Yii::t('common', 'Изображение'),
                self::TYPE_VIDEO => Yii::t('common', 'Видео'),
            ];
            // Кэшируем на 24 часа (86400 секунд)
            Yii::$app->cache->set($cacheKey, $cached, 86400);
        }
        
        return $cached;
    }

    /**
     * Получает активные стикеры, отсортированные по sort
     * 
     * @return static[]
     */
    public static function getActive()
    {
        return static::find()
            ->where(['status' => self::STATUS_ACTIVE])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    /**
     * {@inheritdoc}
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        // Инвалидируем кэш списка стикеров
        Yii::$app->cache->delete('api_support_stickers');
    }

    /**
     * {@inheritdoc}
     */
    public function afterDelete()
    {
        parent::afterDelete();
        // Инвалидируем кэш списка стикеров
        Yii::$app->cache->delete('api_support_stickers');
    }
}

