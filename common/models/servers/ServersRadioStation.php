<?php

namespace common\models\servers;

use Yii;

/**
 * This is the model class for table "servers_radio_stations".
 *
 * @property int $id
 * @property string $name Название радиостанции
 * @property string $url URL потока радиостанции
 * @property string|null $logo URL логотипа на S3
 * @property int $sort Порядок сортировки
 * @property int $status Статус (1 - активна, 0 - неактивна)
 * @property int $created_at
 * @property int $updated_at
 */
class ServersRadioStation extends \common\components\base\ActiveRecord
{
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'servers_radio_stations';
    }

    /**
     * Виртуальное свойство для загрузки файла
     */
    public $logoFile;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'url'], 'required'],
            [['sort', 'status', 'created_at', 'updated_at'], 'integer'],
            [['name'], 'string', 'max' => 255],
            [['url', 'logo'], 'string', 'max' => 500],
            [['url'], 'url', 'defaultScheme' => 'https'],
            [['logoFile'], 'file', 'skipOnEmpty' => true, 'extensions' => 'png, jpg, jpeg, gif, webp', 'maxSize' => 5 * 1024 * 1024],
            [['sort'], 'default', 'value' => 0],
            [['status'], 'default', 'value' => self::STATUS_ACTIVE],
            [['status'], 'in', 'range' => [self::STATUS_INACTIVE, self::STATUS_ACTIVE]],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('common', 'ID'),
            'name' => Yii::t('common', 'Название радиостанции'),
            'url' => Yii::t('common', 'URL потока'),
            'logo' => Yii::t('common', 'Логотип (S3)'),
            'logoFile' => Yii::t('common', 'Логотип'),
            'sort' => Yii::t('common', 'Порядок сортировки'),
            'status' => Yii::t('common', 'Статус'),
            'created_at' => Yii::t('common', 'Создан'),
            'updated_at' => Yii::t('common', 'Обновлен'),
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
     * Получает список статусов
     * 
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_INACTIVE => Yii::t('common', 'Неактивна'),
            self::STATUS_ACTIVE => Yii::t('common', 'Активна'),
        ];
    }

    /**
     * Получает публичный URL для логотипа в S3
     * 
     * @return string|null Публичный URL или null
     */
    public function getLogoUrl()
    {
        if (empty($this->logo)) {
            return null;
        }
        
        $s3Api = Yii::$app->s3Api;
        return $s3Api->getPublicUrl($this->logo);
    }

    /**
     * Сохранение записи (для совместимости с CrudController)
     * 
     * @return bool
     */
    public function saveRecord()
    {
        return $this->save();
    }
}

