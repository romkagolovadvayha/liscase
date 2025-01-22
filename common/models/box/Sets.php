<?php

namespace common\models\box;

use Yii;
use common\components\base\ActiveRecord;

/**
 * @property int        $id
 * @property string     $name
 * @property string     $eng_name
 * @property string     $description
 * @property int        $discount
 * @property float      $price
 * @property int        $status
 * @property int        $sort
 * @property bool       $show_main_block
 * @property string     $created_at
 *
 * @property SetsDrop[]  $setsDrop
 * @property SetsImage   $imageOrig
 * @property SetsImage[] $setsImages
 * @property SetsImage   $imageOrig2
 */
class Sets extends ActiveRecord
{
    const STATUS_NOT_ACTIVE   = 0;
    const STATUS_ACTIVE       = 1;

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
        return 'sets';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'          => Yii::t('common', 'ID'),
            'name'        => Yii::t('common', 'Название'),
            'description' => Yii::t('common', 'Описание'),
            'discount'    => Yii::t('common', 'Скидка'),
            'price'       => Yii::t('common', 'Цена'),
            'status'      => Yii::t('common', 'Статус'),
            'show_main_block'              => Yii::t('common', 'Показывать в главном блоке главной страницы'),
            'created_at'  => Yii::t('common', 'Дата создания'),
        ];
    }

    public function rules(): array
    {
        return [
            [['name', 'price', 'status', 'created_at'], 'required'],
            [['status', 'discount', 'show_main_block'], 'integer'],
            [['name'], 'string', 'max' => 255],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * Gets query for [[User]].
     * Gets query for [[SetsDrop]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSetsDrop()
    {
        return $this->hasMany(SetsDrop::class, ['sets_id' => 'id']);
    }

    /**
     * Gets query for [ImageOrig].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getImageOrig()
    {
        return $this->hasOne(SetsImage::class, ['sets_id' => 'id'])
                    ->cache(60)
                    ->andWhere(['type' => SetsImage::TYPE_ORIG]);
    }
    /**
     * Gets query for [imageOrig2].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getImageOrig2()
    {
        return $this->hasOne(SetsImage::class, ['sets_id' => 'id'])
                    ->cache(60)
                    ->andWhere(['type' => SetsImage::TYPE_ORIG_2]);
    }

    /**
     * @throws \Exception
     */
    public function saveRecord(): bool
    {
        try {
            $this->save(false);
        } catch (\Exception $e) {
            \Yii::info("box file string not save " . print_r($e->getMessage(), 1), 'problem');
            return false;
        }
        return true;
    }

    public static function getList() {
        /** @var Sets[] $models */
        $models = Sets::find()
                     ->orderBy(['sort' => SORT_ASC])
                     ->all();

        $result = [];
        foreach ($models as $item) {
            $result[$item->id] = $item->name;
        }
        return $result;
    }

    public function getRealPrice()
    {
        return ceil($this->price - ($this->price * $this->discount / 100));
    }

    /**
     * Gets query for [[setsImages]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSetsImages()
    {
        return $this->hasMany(SetsImage::class, ['sets_id' => 'id']);
    }

    /**
     *
     * @return Sets[]
     */
    public static function getSetsForMarket($mainBlock = false, $update = false)
    {
        $cacheKey = 'getSetsForMarket3_' . $mainBlock;
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }

        $result = Sets::find()
                      ->andWhere(['status' => Drop::STATUS_ACTIVE])
                      ->andWhere(['show_main_block' => $mainBlock])
                      ->with('setsImages')  // Добавляем кэшируемые связи
                      ->all();

        Yii::$app->cache->set($cacheKey, $result, 7*24*60*60);
        return $result;
    }

    /**
     * Получить URL изображения.
     * Загружает и кэширует изображение, если оно не было загружено ранее.
     */
    public function image() {
        foreach ($this->setsImages as $item) {
            if ($item->type === SetsImage::TYPE_ORIG) {
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
        foreach ($this->setsImages as $item) {
            if ($item->type === SetsImage::TYPE_ORIG_2) {
                return $item->getImagePubUrl();
            }
        }
        return $this->image();
    }
}
