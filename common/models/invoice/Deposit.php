<?php

namespace common\models\invoice;

use common\components\payments\PaymentApi;
use common\models\profit\Profit;
use Yii;
use common\models\user\User;
use yii\base\BaseObject;

/**
 * This is the model class for table "deposit".
 *
 * @property int    $id
 * @property int    $user_id
 * @property int    $payment_type
 * @property int    $amount
 * @property string $payment_id
 * @property int    $status
 * @property string $created_at
 *
 * @property User $user
 */
class Deposit extends \common\components\base\ActiveRecord
{
    const TYPE_PAYMENT_CARD         = 1;
    const TYPE_PAYMENT_SBP          = 2;
    const TYPE_PAYMENT_TRON         = 7;
    const TYPE_PAYMENT_ERC20        = 8;
    const TYPE_PAYMENT_TRC20        = 9;
    const TYPE_PAYMENT_YOOONEY        = 10;
    const TYPE_PAYMENT_STEAM_PAY        = 11;
    const TYPE_PAYMENT_VISA        = 12;
    const TYPE_PAYMENT_MIR        = 13;
    const TYPE_PAYMENT_PERFECT_MONEY        = 14;

    const STATUS_WAIT_CONFIRM = 1;
    const STATUS_CANCELED     = 2;
    const STATUS_SUCCESS      = 3;

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_WAIT_CONFIRM         => Yii::t('common', 'Ожидает оплаты'),
            self::STATUS_CANCELED         => Yii::t('common', 'Отмена'),
            self::STATUS_SUCCESS         => Yii::t('common', 'Проведена'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'deposit';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'payment_type', 'amount', 'status'], 'required'],
            [['user_id', 'payment_type'], 'integer'],
            [['payment_id'], 'trim'],
            [['amount'], 'integer', 'min' => 1],
            [['created_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'           => 'ID',
            'user_id'      => Yii::t('common', 'ID пользователя'),
            'payment_type' => Yii::t('common', 'Метод оплаты'),
            'status'       => Yii::t('common', 'Статус'),
            'payment_id'   => Yii::t('common', 'ID платежа'),
            'amount'       => Yii::t('common', 'Сумма'),
            'created_at'   => Yii::t('common', 'Дата операции'),
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
     * @param      $paymentType
     * @param null $paymentId
     *
     * @return Deposit
     */
    public static function createOperation($userId, $amount, $paymentType, $paymentId = null)
    {
        $model = new Deposit();
        $model->user_id = $userId;
        $model->payment_type = $paymentType;
        $model->payment_id = $paymentId;
        $model->amount = $amount;
        $model->status = Deposit::STATUS_WAIT_CONFIRM;
        $model->created_at = date('Y-m-d H:i:s');
        $model->save(false);
        return $model;
    }

    /**
     * @return array
     */
    public static function getTypeList()
    {
        return [
            self::TYPE_PAYMENT_CARD      => Yii::t('common', 'Оплата картой'),
            self::TYPE_PAYMENT_SBP       => Yii::t('common', 'Оплата по СБП'),
//            self::TYPE_PAYMENT_TRON      => Yii::t('common', 'Оплата TRON'),
//            self::TYPE_PAYMENT_TRC20     => Yii::t('common', 'Оплата TRC20'),
//            self::TYPE_PAYMENT_ERC20     => Yii::t('common', 'Оплата ERC20'),
//            self::TYPE_PAYMENT_YOOONEY     => Yii::t('common', 'Оплата ЮMoney'),
//            self::TYPE_PAYMENT_STEAM_PAY     => Yii::t('common', 'Оплата Steam Pay'),
//            self::TYPE_PAYMENT_VISA     => Yii::t('common', 'Оплата картой Visa'),
//            self::TYPE_PAYMENT_MIR     => Yii::t('common', 'Оплата картой МИР'),
//            self::TYPE_PAYMENT_PERFECT_MONEY     => Yii::t('common', 'Оплата Perfect Money'),
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
//            self::TYPE_PAYMENT_YOOONEY     => '/images/payments/iomoney.png',
//            self::TYPE_PAYMENT_STEAM_PAY     => '/images/payments/scp_logo.png',
//            self::TYPE_PAYMENT_VISA     => '/images/payments/tron.svg',
//            self::TYPE_PAYMENT_MIR     => '/images/payments/tron.svg',
//            self::TYPE_PAYMENT_PERFECT_MONEY     => '/images/payments/Perfect_Money.png',
//            self::TYPE_PAYMENT_TRON      => '/images/payments/tron.svg',
//            self::TYPE_PAYMENT_TRC20     => '/images/payments/tether.svg',
//            self::TYPE_PAYMENT_ERC20     => '/images/payments/tether.svg',
        ];
    }

    /**
     * @return array
     */
    public static function getShortNameList()
    {
        return [
            self::TYPE_PAYMENT_TRC20     => 'TRC20',
            self::TYPE_PAYMENT_ERC20     => 'ERC20',
            self::TYPE_PAYMENT_TRON     => 'TRX',
        ];
    }

    /**
     * @return array
     */
    public static function getLimits()
    {
        return [
            self::TYPE_PAYMENT_CARD          => [1, 100000],
            self::TYPE_PAYMENT_SBP           => [1, 100000],
            self::TYPE_PAYMENT_TRON          => [1000, 100000],
            self::TYPE_PAYMENT_TRC20         => [1000, 100000],
            self::TYPE_PAYMENT_ERC20         => [1000, 100000],
            self::TYPE_PAYMENT_YOOONEY       => [1000, 100000],
            self::TYPE_PAYMENT_STEAM_PAY     => [1000, 100000],
//            self::TYPE_PAYMENT_VISA          => [1000, 100000],
//            self::TYPE_PAYMENT_MIR           => [1000, 100000],
            self::TYPE_PAYMENT_PERFECT_MONEY => [1000, 100000],
        ];
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function check()
    {
        $paymentApi = PaymentApi::getInstance($this->payment_type);
        return $paymentApi->check($this->id);
    }

    /**
     * @param User $user
     * @param $amount
     *
     * @return mixed
     */
    public static function bonus($user, $amount)
    {
        $bonus = 0;
        if ($amount >= 5000) {
            $bonus = $amount * 0.5;
        } elseif ($amount >= 2000) {
            $bonus = $amount * 0.3;
        } elseif ($amount >= 1500) {
            $bonus = $amount * 0.25;
        } elseif ($amount >= 1000) {
            $bonus = $amount * 0.2;
        } elseif ($amount >= 500) {
            $bonus = $amount * 0.15;
        }

        if ($bonus > 0) {
            return 0;
        }

        $profit = new Profit();
        $profit->status = 1;
        $profit->type = Profit::TYPE_BONUS;
        $profit->amount = ceil($bonus);
        $profit->user_balance_id = $user->getPersonalBalance()->id;
        $profit->comment = Yii::t('common', 'Бонус при пополнении');
        $profit->created_at = date('Y-m-d H:i:s');
        return $profit->save(false);
    }
}
