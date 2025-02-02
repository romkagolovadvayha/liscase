<?php

namespace common\models\promocode;

use common\components\web\Cookie;
use common\models\user\UserPromocode;
use Yii;
use yii\base\BaseObject;

/**
 * This is the model class for table "promocode".
 *
 * @property int    $id
 * @property string $code
 * @property int    $type
 * @property int    $status
 * @property string $amount
 * @property string $finished_at
 * @property string $created_at
 */
class Promocode extends \common\components\base\ActiveRecord
{
    const TYPE_PAYMENT_DISCOUNT  = 1;
    const TYPE_PAYMENT_PAYMENT   = 2;

    const STATUS_NOT_ACTIVE    = 0;
    const STATUS_ACTIVE  = 1;
    const STATUS_USED    = 2;

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_NOT_ACTIVE      => Yii::t('common', 'Не активен'),
            self::STATUS_ACTIVE      => Yii::t('common', 'Активен'),
            self::STATUS_USED       => Yii::t('common', 'Использован'),
        ];
    }

    /**
     * @return array
     */
    public static function getTypeList()
    {
        return [
            self::TYPE_PAYMENT_DISCOUNT      => Yii::t('common', 'Скидка'),
            self::TYPE_PAYMENT_PAYMENT       => Yii::t('common', 'К пополнению'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'promocode';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'amount', 'finished_at', 'code'], 'required'],
            [['type', 'status', 'amount'], 'integer'],
            [['created_at', 'finished_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'            => 'ID',
            'code'       => Yii::t('common', 'Промокод'),
            'type'          => Yii::t('common', 'Тип'),
            'amount'       => Yii::t('common', 'Сумма'),
            'status'        => Yii::t('common', 'Статус'),
            'finished_at'          => Yii::t('common', 'Дата завершения'),
            'created_at'    => Yii::t('common', 'Дата начала'),
        ];
    }

    /**
     * @param      $code
     * @param      $amount
     * @param null $finishedAt
     *
     * @return string
     */
    public static function createRecord($code, $amount, $finishedAt = null)
    {
        $model = new Promocode();
        $model->amount = $amount;
        $model->type = self::TYPE_PAYMENT_PAYMENT;
        $model->code = $code;
        $model->finished_at = $finishedAt;
        $model->status = self::STATUS_ACTIVE;
        $model->created_at = date('Y-m-d H:i:s');
        $model->save(false);
        return Yii::$app->db->getLastInsertID();
    }

    /**
     * @param $code
     *
     * @return Promocode|\yii\db\ActiveRecord|null
     */
    public static function findByCode($code)
    {
        return Promocode::find()
                        ->andWhere(['code' => $code])
                        ->one();
    }

}
