<?php

namespace common\models\box;

use common\models\promocode\Promocode;
use common\models\user\UserBox;
use Yii;
use common\components\base\ActiveRecord;

/**
 * @property int        $id
 * @property string     $name
 * @property int        $type
 * @property float      $price
 * @property int        $status
 * @property string     $created_at
 *
 * @property BoxDrop[]  $boxDrop
 * @property BoxDrop[]  $boxDropCarousel
 * @property BoxImage[] $boxImages
 * @property BoxImage   $imageOrig
 */
class Box extends ActiveRecord
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
     * @return array
     */
    public static function getTypeList(): array
    {
        return [
            self::TYPE_DEFAULT       => Yii::t('common', 'Стандартная рулетка'),
            self::TYPE_FREE       => Yii::t('common', 'Бесплатные кейсы'),
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'box';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'name'               => Yii::t('common', 'Название'),
            'type'               => Yii::t('common', 'Тип'),
            'price'              => Yii::t('common', 'Цена'),
            'status'              => Yii::t('common', 'Статус'),
            'created_at'          => Yii::t('common', 'Дата создания'),
        ];
    }

    public function rules(): array
    {
        return [
            [['name', 'price', 'status', 'type', 'created_at'], 'required'],
            [['status'], 'integer'],
            [['name'], 'string', 'max' => 255],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * Gets query for [[User]].
     * Gets query for [[BoxDrop]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBoxDrop()
    {
        return $this->hasMany(BoxDrop::class, ['box_id' => 'id']);
    }

    /**
     * Gets query for [[User]].
     * Gets query for [[BoxDrop]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBoxDropCarousel()
    {
        /** @var BoxDrop[] $boxDrops */
        $boxDrops = $this->hasMany(BoxDrop::class, ['box_id' => 'id'])->all();
        $result = [];
        foreach ($boxDrops as $boxDrop) {
            if ($boxDrop->drop->price < 30) {
                for ($i = 0; $i < 5; $i++) {
                    $result[] = $boxDrop;
                }
            }
            if ($boxDrop->drop->price < 50) {
                for ($i = 0; $i < 5; $i++) {
                    $result[] = $boxDrop;
                }
            }
            if ($boxDrop->drop->price < 100) {
                for ($i = 0; $i < 5; $i++) {
                    $result[] = $boxDrop;
                }
            }
            if ($boxDrop->drop->price < 300) {
                for ($i = 0; $i < 5; $i++) {
                    $result[] = $boxDrop;
                }
            }
            if ($boxDrop->drop->price < 500) {
                for ($i = 0; $i < 5; $i++) {
                    $result[] = $boxDrop;
                }
            }
            if ($boxDrop->drop->price < 1000) {
                for ($i = 0; $i < 5; $i++) {
                    $result[] = $boxDrop;
                }
            }
            if ($boxDrop->drop->price < 3000) {
                for ($i = 0; $i < 10; $i++) {
                    $result[] = $boxDrop;
                }
            }
            if ($boxDrop->drop->price >= 3000) {
                for ($i = 0; $i < 10; $i++) {
                    $result[] = $boxDrop;
                }
            }
        }
        shuffle($result);
        shuffle($result);

        return $result;
    }

    /**
     * Gets query for [ImageOrig].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getImageOrig()
    {
        return $this->hasOne(BoxImage::class, ['box_id' => 'id'])
                    ->andWhere(['type' => BoxImage::TYPE_ORIG]);
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
     * Gets query for [[BoxImages]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBoxImages()
    {
        return $this->hasMany(BoxImage::class, ['box_id' => 'id']);
    }

    /**
     * @param $type
     *
     * @return Box[]
     */
    public static function getBoxesByType($type)
    {
        return Box::find()
                  ->andWhere(['type' => $type])
                  ->andWhere(['status' => Box::STATUS_ACTIVE])
                  ->all();
    }

    /**
     * @return float
     */
    public function getPriceFinal() {
        $price = $this->price;
        $promocode = Promocode::getActivePromocode();
        if (!empty($promocode)) {
            $price = ceil($promocode->percent / 100 * $this->price);
        }
        if ($this->type === Box::TYPE_FREE) {
            return 0;
        }
        return $price;
    }

    /**
     * @return UserBox|\yii\db\ActiveRecord|null
     */
    public static function getLastOpenFreeBox($userId = null) {
        if (empty($userId)) {
            $userId = Yii::$app->user->id;
        }
        return UserBox::find()
                      ->alias('ub')
                      ->joinWith('box b')
                      ->andWhere(['b.type' => Box::TYPE_FREE])
                      ->andWhere(['ub.user_id' => $userId])
                      ->orderBy(['id' => SORT_DESC])
                      ->one();
    }

    /**
     * @return string|null
     * @throws \Exception
     */
    public static function getNextOpenFreeBoxDate() {
        $last = Box::getLastOpenFreeBox();
        if (empty($last)) {
            return null;
        }
        $createdDate = new \DateTime($last->created_at);
        $createdDate->modify('+1 day');

        if ($createdDate->getTimestamp() < time()) {
            return null;
        }

        return $createdDate->format('Y-m-d H:i:s');
    }

    /**
     * @param $userBox
     */
    public function _getDropFinal() {
        [$boxDropCarousel, $number] = $this->_getDrop();
        if ($boxDropCarousel[$number]->drop->price > 1000) {
            [$boxDropCarousel, $number] = $this->_getDrop();
        }
        if ($boxDropCarousel[$number]->drop->price > 2000) {
            [$boxDropCarousel, $number] = $this->_getDrop();
        }
        return [$boxDropCarousel, $number];
    }

    /**
     * @param $userBox
     *
     * @return array
     */
    public function _getDrop() {
        $boxDropCarousel = $this->boxDropCarousel;
        $number = rand(count($boxDropCarousel) / 2, count($boxDropCarousel) - 1);

        return [$boxDropCarousel, $number];
    }
}
