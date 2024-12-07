<?php

namespace common\models\invoice;

use common\components\payments\PaymentApi;
use common\models\profit\Profit;
use Yii;
use common\models\user\User;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;

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
    const TYPE_PAYMENT_CARD_UA        = 15;
    const TYPE_PAYMENT_CARD_KZT        = 16;
    const TYPE_PAYMENT_CARD_YM        = 17;

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
            self::TYPE_PAYMENT_TRC20     => Yii::t('common', 'Оплата TRC20'),
            self::TYPE_PAYMENT_ERC20     => Yii::t('common', 'Оплата ERC20'),
//            self::TYPE_PAYMENT_YOOONEY     => Yii::t('common', 'Оплата ЮMoney'),
//            self::TYPE_PAYMENT_STEAM_PAY     => Yii::t('common', 'Оплата Steam Pay'),
//            self::TYPE_PAYMENT_VISA     => Yii::t('common', 'Оплата картой Visa'),
//            self::TYPE_PAYMENT_MIR     => Yii::t('common', 'Оплата картой МИР'),
            self::TYPE_PAYMENT_PERFECT_MONEY     => Yii::t('common', 'Оплата Perfect Money'),
            self::TYPE_PAYMENT_CARD_UA     => Yii::t('common', 'Оплата картой UA'),
            self::TYPE_PAYMENT_CARD_KZT     => Yii::t('common', 'Оплата картой Казахстан'),
            self::TYPE_PAYMENT_CARD_YM     => Yii::t('common', 'Оплата ЮMoney'),
        ];
    }

    /**
     * @return array
     */
    public static function getIconTypeList()
    {
        $icons = [
            self::TYPE_PAYMENT_CARD      => '/images/payments/cards.svg',
            self::TYPE_PAYMENT_SBP       => '/images/payments/sbp.svg',
//            self::TYPE_PAYMENT_YOOONEY     => '/images/payments/iomoney.png',
//            self::TYPE_PAYMENT_STEAM_PAY     => '/images/payments/scp_logo.png',
//            self::TYPE_PAYMENT_VISA     => '/images/payments/tron.svg',
//            self::TYPE_PAYMENT_MIR     => '/images/payments/tron.svg',
            self::TYPE_PAYMENT_PERFECT_MONEY     => '/images/payments/Perfect_Money.png',
//            self::TYPE_PAYMENT_TRON      => '/images/payments/tron.svg',
            self::TYPE_PAYMENT_TRC20     => '/images/payments/tether.svg',
//            self::TYPE_PAYMENT_ERC20     => '/images/payments/tether.svg',
            self::TYPE_PAYMENT_CARD_UA     => '/images/payments/cards.svg',
            self::TYPE_PAYMENT_CARD_KZT     => '/images/payments/cards.svg',
            self::TYPE_PAYMENT_CARD_YM     => '/images/payments/iomoney.png',
        ];

        if (Yii::$app->language !== 'ru-RU') {
            unset($icons[self::TYPE_PAYMENT_CARD]);
            unset($icons[self::TYPE_PAYMENT_SBP]);
            unset($icons[self::TYPE_PAYMENT_CARD_UA]);
            unset($icons[self::TYPE_PAYMENT_CARD_KZT]);
        }

        return $icons;
    }

    /**
     * @return array
     */
    public static function getShortNameList()
    {
        return [
//            self::TYPE_PAYMENT_TRC20     => 'TRC20',
//            self::TYPE_PAYMENT_ERC20     => 'ERC20',
//            self::TYPE_PAYMENT_TRON     => 'TRX',
            self::TYPE_PAYMENT_CARD_UA     => '<div style="font-size: 14px">Украина</div>',
            self::TYPE_PAYMENT_CARD_KZT     => '<div style="font-size: 14px">Казахстан</div>',
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
//            self::TYPE_PAYMENT_TRON          => [1000, 100000],
            self::TYPE_PAYMENT_TRC20         => [300, 100000],
//            self::TYPE_PAYMENT_ERC20         => [1000, 100000],
            self::TYPE_PAYMENT_YOOONEY       => [1000, 100000],
            self::TYPE_PAYMENT_STEAM_PAY     => [1000, 100000],
//            self::TYPE_PAYMENT_VISA          => [1000, 100000],
//            self::TYPE_PAYMENT_MIR           => [1000, 100000],
            self::TYPE_PAYMENT_PERFECT_MONEY => [1000, 100000],
            self::TYPE_PAYMENT_CARD_UA => [1000, 100000],
            self::TYPE_PAYMENT_CARD_KZT => [1000, 100000],
            self::TYPE_PAYMENT_CARD_YM => [1000, 100000],
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
     * @return mixed
     * @throws \Exception
     */
    public function debugCheck()
    {
        $paymentApi = PaymentApi::getInstance($this->payment_type);
        return $paymentApi->debugCheck($this->id);
    }

    /**
     * @param User $user
     * @param $amount
     * @param $paymentType
     *
     * @return mixed
     */
    public static function bonus($user, $amount, $paymentType)
    {
        $amountTotalSum = Deposit::find()
                               ->andWhere(['status' => Deposit::STATUS_SUCCESS])
                               ->sum('amount') ?? 0;
        $amountDaySum = Deposit::find()
                            ->andWhere(['>=', 'created_at', date('Y-m-d') . " 00:00:01"])
                            ->andWhere(['<=', 'created_at', date('Y-m-d') . " 23:59:59"])
                            ->andWhere(['status' => Deposit::STATUS_SUCCESS])
                            ->sum('amount') ?? 0;


        $amountStr = number_format($amount, 0, '.', ' ');
        $message = "💰️ <b>Пополнение баланса</b>" . PHP_EOL
            . "Пользователь: {$user->username}" . PHP_EOL
            . "SteamID: {$user->steam_id}" . PHP_EOL
            . "Сумма: {$amountStr} RUB";

        if (!empty($user->server)) {
            $message .= PHP_EOL . "Сервер: {$user->server->name}";
        }

        $depositsSum = Deposit::find()
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['status' => Deposit::STATUS_SUCCESS])
            ->sum('amount') ?? 0;

        $paymentName = ArrayHelper::getValue(Deposit::getTypeList(), $paymentType);
        if (!empty($paymentName)) {
            $message .= PHP_EOL . "Метод оплаты: {$paymentName}";
        }

        $depositsSumStr = number_format($depositsSum, 0, '.', ' ');
        $amountDaySumStr = number_format($amountDaySum, 0, '.', ' ');
        $amountTotalSumStr = number_format($amountTotalSum, 0, '.', ' ');
        $message .= PHP_EOL . PHP_EOL
            . "Поступлений от игрока: {$depositsSumStr} RUB" . PHP_EOL
            . "Всего за день: {$amountDaySumStr} RUB" . PHP_EOL
            . "Всего за всегда: {$amountTotalSumStr} RUB";

        Yii::$app->telegramPayments->sendMessage($message);

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
            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_BONUS;
            $profit->amount = ceil($bonus);
            $profit->user_balance_id = $user->getPersonalBalance()->id;
            $profit->comment = Yii::t('common', 'Бонус при пополнении');
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);
        }
        return true;
    }

    public static function responseAdapter($response, $payment) {
        $data = json_decode($response, 1);
        $status = null;
        switch ($payment) {
            case 'tome':
                if ($data['event'] == 'payment.succeeded') {
                    $status = 'SUCCESS';
                }
                if ($data['event'] == 'payment.canceled') {
                    $status = 'CANCEL';
                }
                return [
                  'id' => $data['object']['id'],
                  'status' => $status
                ];
            case 'anypay':
                $status = 'NO AVAILABLE';
                $transactionId = !empty($data['transaction_id']) ? $data['transaction_id'] : $data['result']['transaction_id'];
                return [
                    'id' => $transactionId,
                    'status' => $status
                ];
            break;
        }
    }

}
