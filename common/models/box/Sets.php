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
 * @property string     $created_at
 *
 * @property SetsDrop[]  $setsDrop
 * @property SetsImage   $imageOrig
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
            'created_at'  => Yii::t('common', 'Дата создания'),
        ];
    }

    public function rules(): array
    {
        return [
            [['name', 'price', 'status', 'created_at'], 'required'],
            [['status', 'discount'], 'integer'],
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
                    ->andWhere(['type' => SetsImage::TYPE_ORIG]);
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
     *
     * @return Sets[]
     */
    public static function getSetsForMarket()
    {
        return Sets::find()
                   ->andWhere(['status' => Drop::STATUS_ACTIVE])
                   ->all();
    }

}
