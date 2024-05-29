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
 * @property string     $created_at
 *
 * @property SelectDrop[]  $selectDrop
 * @property SelectDrop[]  $selectDropCarousel
 * @property SelectImage[] $SelectImages
 * @property SelectImage   $imageOrig
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
            'created_at'          => Yii::t('common', 'Дата создания'),
        ];
    }

    public function rules(): array
    {
        return [
            [['name', 'status', 'created_at'], 'required'],
            [['status'], 'integer'],
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
    public static function getForMarket()
    {
        return Select::find()
                   ->andWhere(['status' => Select::STATUS_ACTIVE])
                   ->all();
    }

}
