<?php

namespace common\models\box;

use common\components\helpers\CurrencyHelper;
use Yii;
use common\components\base\ActiveRecord;

/**
 * @property int        $id
 * @property string     $name
 * @property string     $description
 * @property int        $status
 * @property bool       $show_main_block
 * @property string     $created_at
 *
 * @property SelectDrop[]  $selectDrop
 * @property SelectDrop[]  $selectDropCarousel
 * @property SelectImage[] $selectImages
 * @property SelectImage   $imageOrig
 * @property SelectImage   $imageOrig2
 */
class Select extends ActiveRecord
{
    const STATUS_NOT_ACTIVE   = 0;
    const STATUS_ACTIVE       = 1;

    const TYPE_DEFAULT = 1;
    const TYPE_FREE = 2;

    /**
     * @return array
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_NOT_ACTIVE       => Yii::t('common', 'Не активный'),
            self::STATUS_ACTIVE       => Yii::t('common', 'Активный'),
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'select';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'name'               => Yii::t('common', 'Название'),
            'status'              => Yii::t('common', 'Статус'),
            'show_main_block'              => Yii::t('common', 'Показывать в главном блоке главной страницы'),
            'created_at'          => Yii::t('common', 'Дата создания'),
        ];
    }

    public function rules(): array
    {
        return [
            [['name', 'status', 'created_at'], 'required'],
            [['status', 'show_main_block'], 'integer'],
            [['description'], 'trim'],
            [['name'], 'string', 'max' => 255],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * Gets query for [[SelectDrop]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSelectDrop()
    {
        return $this->hasMany(SelectDrop::class, ['select_id' => 'id']);
    }


    /**
     * Gets query for [ImageOrig].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getImageOrig()
    {
        return $this->hasOne(SelectImage::class, ['select_id' => 'id'])
                    ->andWhere(['type' => SelectImage::TYPE_ORIG]);
    }

    /**
     * Gets query for [ImageOrig2].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getImageOrig2()
    {
        return $this->hasOne(SelectImage::class, ['select_id' => 'id'])
                    ->andWhere(['type' => SelectImage::TYPE_ORIG_2]);
    }

    /**
     * @throws \Exception
     */
    public function saveRecord(): bool
    {
        try {
            $this->save(false);
        } catch (\Exception $e) {
            \Yii::info("Select file string not save " . print_r($e->getMessage(), 1), 'problem');
            return false;
        }
        return true;
    }

    /**
     *
     * @return string
     */
    public function getCurrency()
    {
        return CurrencyHelper::default();
    }

    /**
     * Gets query for [[SelectImages]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSelectImages()
    {
        return $this->hasMany(SelectImage::class, ['select_id' => 'id']);
    }

    /**
     *
     * @return Select[]
     */
    public static function getForMarket($mainBlock = false, $update = false)
    {
        $cacheKey = 'getForMarketSelect3_' . $mainBlock;
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }

        $result = Select::find()
                        ->andWhere(['status' => Select::STATUS_ACTIVE])
                        ->andWhere(['show_main_block' => $mainBlock])
                        ->with('selectImages')  // Добавляем кэшируемые связи
                        ->all();

        Yii::$app->cache->set($cacheKey, $result, 7*24*60*60);
        return $result;
    }

    /**
     * Получить URL изображения.
     * Загружает и кэширует изображение, если оно не было загружено ранее.
     */
    public function image() {
        foreach ($this->selectImages as $item) {
            if ($item->type === SelectImage::TYPE_ORIG) {
                return $item->getImagePubUrl();
            }
        }
        return null;
    }

    /**
     * Получить второй URL изображения.
     * Кэширует значение, чтобы избежать повторных запросов.
     */
    public function image2() {
        foreach ($this->selectImages as $item) {
            if ($item->type === SelectImage::TYPE_ORIG_2) {
                return $item->getImagePubUrl();
            }
        }
        return $this->image();
    }
}
