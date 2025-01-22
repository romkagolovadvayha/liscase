<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use common\components\helpers\CurrencyHelper;
use common\models\statistics\Statistics;
use Yii;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $eng_name
 * @property string      $quality
 * @property string      $market_status
 * @property int         $min_box
 * @property int         $max_box
 * @property string      $description
 * @property string      $market_id
 * @property int         $count
 * @property int         $discount
 * @property int         $category_id
 * @property string      $rust_id
 * @property string      $command
 * @property string      $type_id
 * @property float       $price
 * @property int         $blocked_hour
 * @property string      $blocked_at
 * @property int         $status
 * @property string      $created_at
 * @property bool        $show_main_block
 * @property int         $sort
 *
 * @property DropImage[] $dropImages
 * @property DropImage   $imageOrig
 * @property DropImage   $imageOrig2
 * @property DropType    $type
 * @property string      $priceCeil
 * @property string      $priceMarket
 * @property string      $currency
 * @property BoxDrop     $boxDrop
 */
class Drop extends ActiveRecord
{
    private $_imageOrigUrl;
    private $_imageOrig2Url;

    const STATUS_NOT_ACTIVE   = 0;
    const STATUS_ACTIVE       = 1;
    const MARKET_STATUS_NOT_ACTIVE   = 0;
    const MARKET_STATUS_ACTIVE       = 1;

    /**
     * @return array
     */
    public static function getMarketStatusList(): array
    {
        return [
            self::MARKET_STATUS_NOT_ACTIVE       => Yii::t('common', 'Не активный'),
            self::MARKET_STATUS_ACTIVE       => Yii::t('common', 'Активный'),
        ];
    }
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
        return 'drop';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'name'               => Yii::t('common', 'Название'),
            'eng_name'               => Yii::t('common', 'Название'),
            'quality'               => Yii::t('common', 'Качество'),
            'description'               => Yii::t('common', 'Описание'),
            'category_id'               => Yii::t('common', 'Категория'),
            'market_id'               => Yii::t('common', 'ID в маргете'),
            'rust_id'               => Yii::t('common', 'Индитификатор'),
            'market_status'               => Yii::t('common', 'Статус в магазине'),
            'count'               => Yii::t('common', 'Количество в маркете'),
            'discount'               => Yii::t('common', 'Скидка'),
            'min_box'               => Yii::t('common', 'Минимальное кол-во в рулетке'),
            'max_box'               => Yii::t('common', 'Максимальное кол-во в рулетке'),
            'type_id'               => Yii::t('common', 'Тип'),
            'status'               => Yii::t('common', 'Статус'),
            'price'              => Yii::t('common', 'Цена'),
            'created_at'          => Yii::t('common', 'Дата создания'),
            'command'          => Yii::t('common', 'Команда'),
            'blocked_hour'          => Yii::t('common', 'Вайп блок (часов)'),
            'show_main_block'              => Yii::t('common', 'Показывать в главном блоке главной страницы'),
            'sort'          => Yii::t('common', 'Сортировка'),
        ];
    }

    /**
     * @return mixed|string
     */
    public function getShortName() {
        $array = explode('|', Yii::t('database', $this->name));
        if (count($array) < 2) {
            return Yii::t('database', $this->name);
        }
        $array2 = explode('(', $array[1]);
        if (count($array2) < 2) {
            return trim($array[1]);
        }
        return trim($array2[0]);
    }

    /**
     * @return array
     */
    public static function getQualityList() {
        $all = Drop::find()
            ->cache(24 * 60 * 60)
            ->select('quality')
            ->andWhere(['status' => Drop::STATUS_ACTIVE])
            ->distinct(true)
            ->indexBy('quality')
            ->asArray()
            ->all();

        $result = [];
        foreach ($all as $index => $item) {
            $result[$index] = Yii::t('database', $index);
        }
        return $result;
    }

    /**
     * @return string
     */
    public static function getPriceMax() {
        $result = Drop::find()
                      ->cache(24 * 60 * 60)
                      ->select('MAX(price)')
                      ->andWhere(['status' => Drop::STATUS_ACTIVE])
                      ->createCommand()
                      ->queryScalar();
        return ceil($result);
    }

    /**
     * @return mixed|string
     */
    public function getLevel() {
        $level = 0;
        if ($this->priceCeil > 100) {
            $level = 1;
        }
        if ($this->priceCeil > 500) {
            $level = 2;
        }
        if ($this->priceCeil > 1000) {
            $level = 3;
        }
        return $level;
    }

    public function rules(): array
    {
        return [
            [['status', 'type_id', 'category_id', 'sort', 'show_main_block'], 'integer'],
            [['name', 'market_id', 'eng_name', 'quality'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['created_at','price'], 'safe'],
        ];
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

    /**
     * Gets query for [[DropImages]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getDropImages()
    {
        return $this->hasMany(DropImage::class, ['drop_id' => 'id']);
    }

    /**
     *
     * @return string
     */
    public function getPriceCeil()
    {
        return ceil($this->price);
    }

    /**
     * @return string
     */
    public function getPriceFormat()
    {
        return number_format($this->priceCeil, 0, '.', ' ');
    }

    /**
     *
     * @return string
     */
    public function getPriceMarket()
    {
        return ceil($this->priceCeil);
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
     * Gets query for [ImageOrig].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getImageOrig()
    {
        return $this->hasOne(DropImage::class, ['drop_id' => 'id'])
//            ->cache(300)
            ->andWhere(['type' => DropImage::TYPE_ORIG]);
    }

    /**
     * Gets query for [ImageOrig2].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getImageOrig2()
    {
        return $this->hasOne(DropImage::class, ['drop_id' => 'id'])
//            ->cache(300)
            ->andWhere(['type' => DropImage::TYPE_ORIG_2]);
    }

    /**
     *
     * @return Drop[]
     */
    public static function getForMarket($mainBlock = false, $update = false)
    {
        $cacheKey = 'getForMarketDrop3_' . $mainBlock;
        if (Yii::$app->cache->get($cacheKey) && !$update) {
            return Yii::$app->cache->get($cacheKey);
        }

        $result = Drop::find()
                      ->andWhere(['market_status' => Drop::MARKET_STATUS_ACTIVE])
                      ->andWhere(['show_main_block' => $mainBlock])
                      ->orderBy(['sort' => SORT_ASC])
                      ->with('dropImages', 'type')  // Добавляем кэшируемые связи
                      ->all();

        Yii::$app->cache->set($cacheKey, $result, 7*24*60*60);
        return $result;
    }

    /**
     * Gets query for [Type].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getType()
    {
        return $this->hasOne(DropType::class, ['id' => 'type_id']);
    }

    public function getRealPrice()
    {
        return ceil($this->price - ($this->price * $this->discount / 100));
    }

    /**
     * Gets query for [BoxDrop].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBoxDrop()
    {
        return $this->hasMany(BoxDrop::class, ['drop_id' => 'id'])
            ->joinWith('box b')
            ->andWhere(['NOT IN', 'b.type', [Box::TYPE_FREE]])
            ->andWhere(['b.status' => Box::STATUS_ACTIVE]);
    }

    public static function getList() {
        /** @var Drop[] $drops */
        $drops = Drop::find()
                     ->orderBy(['price' => SORT_ASC])
                     ->all();

        $result = [];
        foreach ($drops as $item) {
            $result[$item->id] = json_encode([
                                                 'name' => $item->name,
                                                 'image' => $item->imageOrig->getImagePubUrl(),
                                             ]);
        }
        return $result;
    }

    public function blocked() {
        return !empty($this->blocked_at) && strtotime($this->blocked_at) > time();
    }

    public function blockedTime() {
        return strtotime($this->blocked_at);
    }

    /**
     * Получить URL изображения.
     * Загружает и кэширует изображение, если оно не было загружено ранее.
     */
    public function image() {
        foreach ($this->dropImages as $item) {
            if ($item->type === DropImage::TYPE_ORIG) {
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
        foreach ($this->dropImages as $item) {
            if ($item->type === DropImage::TYPE_ORIG_2) {
                return $item->getImagePubUrl();
            }
        }
        return $this->image();
    }
}
