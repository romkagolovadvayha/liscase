<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use common\components\helpers\CurrencyHelper;
use Yii;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $eng_name
 * @property string      $quality
 * @property string      $description
 * @property string      $market_id
 * @property string      $type_id
 * @property float       $price
 * @property int         $status
 * @property string      $created_at
 *
 * @property DropImage[] $dropImages
 * @property DropImage   $imageOrig
 * @property DropType    $type
 * @property string      $priceCeil
 * @property string      $priceMarket
 * @property string      $currency
 * @property BoxDrop     $boxDrop
 */
class Drop extends ActiveRecord
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
            'market_id'               => Yii::t('common', 'ID в маргете'),
            'type_id'               => Yii::t('common', 'Тип'),
            'price'              => Yii::t('common', 'Цена'),
            'status'              => Yii::t('common', 'Статус'),
            'created_at'          => Yii::t('common', 'Дата создания'),
        ];
    }

    /**
     * @return mixed|string
     */
    public function getShortName() {
        $array = explode('|', $this->name);
        if (count($array) < 2) {
            return $this->name;
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
            $result[$index] = $index;
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
            [['status', 'type_id'], 'integer'],
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
        return ceil($this->priceCeil * 1.05);
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
            ->andWhere(['type' => DropImage::TYPE_ORIG]);
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

    /**
     * @throws \Exception
     */
    public function importMarket()
    {
        $item = Yii::$app->marketApi->getParserItemById($this->market_id);
        $this->name = $item['name'];
        $this->eng_name = $item['market_hash_name'];
        $this->price = ceil($item['min_price']/100);
        $this->quality = $item['quality'];
        if (empty($this->description)) {
            $this->description = "";
            foreach ($item['description'] as $index => $itemDesc) {
                if ($index === 0 || empty($itemDesc['value'])) {
                    continue;
                }
                $this->description .= $itemDesc['value'];
            }
        }
        $this->type_id = DropType::createRecord($item['type'], $item['mtype']);
    }

    public static function getList() {
        /** @var Drop[] $drops */
        $drops = Drop::find()
                     ->orderBy(['price' => SORT_ASC])
                     ->all();

        $result = [];
        foreach ($drops as $item) {
            $result[$item->id] = $item->imageOrig->getImagePubUrl();
        }
        return $result;
    }
}
