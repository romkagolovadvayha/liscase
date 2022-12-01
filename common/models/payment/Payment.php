<?php

namespace common\models\payment;

use Yii;
use common\models\user\User;
use yii\base\BaseObject;

/**
 * This is the model class for table "payment".
 *
 * @property int    $id
 * @property int    $user_id
 * @property int    $promocode_id
 * @property int    $type
 * @property int    $status
 * @property string $service
 * @property string $amount
 * @property string $created_at
 *
 * @property User   $user
 */
class Payment extends \common\components\base\ActiveRecord
{
    const TYPE_PAYMENT_CARD         = 1;
    const TYPE_PAYMENT_SBP          = 2;
    const TYPE_PAYMENT_QIWI         = 3;
    const TYPE_PAYMENT_SKINSBACK    = 4;
    const TYPE_PAYMENT_ETH          = 5;
    const TYPE_PAYMENT_BTC          = 6;
    const TYPE_PAYMENT_TRON         = 7;
    const TYPE_PAYMENT_ERC20        = 8;
    const TYPE_PAYMENT_TRC20        = 9;

    const STATUS_CREATE  = 1;
    const STATUS_WAIT    = 2;
    const STATUS_SUCCESS = 3;
    const STATUS_FAIL    = 4;

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_CREATE      => Yii::t('common', 'Создан'),
            self::STATUS_WAIT       => Yii::t('common', 'Ожидает оплату'),
            self::STATUS_SUCCESS      => Yii::t('common', 'Оплата прошла успешно'),
            self::STATUS_FAIL => Yii::t('common', 'Ошибка или отмена операции'),
        ];
    }
    /**
     * @return array
     */
    public static function getTypeList()
    {
        return [
            self::TYPE_PAYMENT_CARD      => Yii::t('common', 'Оплата картой'),
            self::TYPE_PAYMENT_SBP       => Yii::t('common', 'Оплата по СБП'),
            self::TYPE_PAYMENT_QIWI      => Yii::t('common', 'Оплата QIWI'),
            self::TYPE_PAYMENT_SKINSBACK => Yii::t('common', 'Оплата SKINSBACK'),
            self::TYPE_PAYMENT_ETH       => Yii::t('common', 'Оплата ETH'),
            self::TYPE_PAYMENT_BTC       => Yii::t('common', 'Оплата BTC'),
            self::TYPE_PAYMENT_TRON      => Yii::t('common', 'Оплата TRON'),
            self::TYPE_PAYMENT_ERC20     => Yii::t('common', 'Оплата ERC20'),
            self::TYPE_PAYMENT_TRC20     => Yii::t('common', 'Оплата TRC20'),
        ];
    }

    /**
     * @return array
     */
    public static function getIconTypeList()
    {
        return [
            self::TYPE_PAYMENT_CARD      => '/images/payments/cards.svg',
            self::TYPE_PAYMENT_SBP       => '/images/payments/sbp.svg',
            self::TYPE_PAYMENT_QIWI      => '/images/payments/qiwi.svg',
            self::TYPE_PAYMENT_SKINSBACK => '/images/payments/skinsback.svg',
            self::TYPE_PAYMENT_ETH       => '/images/payments/ethereum.svg',
            self::TYPE_PAYMENT_BTC       => '/images/payments/bitcoin.svg',
            self::TYPE_PAYMENT_TRON      => '/images/payments/tron.svg',
            self::TYPE_PAYMENT_ERC20     => '/images/payments/tether.svg',
            self::TYPE_PAYMENT_TRC20     => '/images/payments/tether.svg',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'payment';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'type', 'amount'], 'required'],
            [['user_id', 'type', 'status', 'promocode_id'], 'integer'],
            [['service'], 'string'],
            [['amount'], 'number', 'min' => 0.01],
            [['created_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'            => 'ID',
            'user_id'        => Yii::t('common', 'ID пользователя'),
            'promocode_id'        => Yii::t('common', 'ID промокода'),
            'type'          => Yii::t('common', 'Тип'),
            'amount'        => Yii::t('common', 'Сумма'),
            'status'        => Yii::t('common', 'Статус'),
            'service'        => Yii::t('common', 'Сервис'),
            'created_at'    => Yii::t('common', 'Дата операции'),
        ];
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        $this->user->getPersonalBalance()->recalculateBalance();
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * @param      $userId
     * @param      $amount
     * @param      $type
     * @param      $service
     * @param null $status
     * @param null $promocodeId
     *
     * @return string
     */
    public static function createRecord($userId, $amount, $type, $service, $status = null, $promocodeId = null)
    {
        $model = new Payment();
        $model->user_id = $userId;
        $model->amount = $amount;
        $model->service = $service;
        $model->type = $type;
        $model->promocode_id = $promocodeId;
        $model->status = self::STATUS_CREATE;
        if (empty($status)) {
            $model->status = $status;
        }
        $model->created_at = date('Y-m-d H:i:s');
        $model->save(false);
        return Yii::$app->db->getLastInsertID();
    }
}
