<?php

namespace common\models\invoice;

use common\components\payments\PaymentApi;
use common\models\profit\Profit;
use Yii;
use common\models\user\User;
use yii\base\BaseObject;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * This is the model class for table "deposit".
 *
 * @property int    $id
 * @property int    $user_id
 * @property int    $payment_type
 * @property int    $amount
 * @property int    $amount_exchange
 * @property string $payment_id
 * @property float  $commission
 * @property int    $status
 * @property string $created_at
 * @property string|null $completed_at
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
    const TYPE_PAYMENT_CARD_TINKOFF        = 18;
    const TYPE_PAYMENT_TON        = 19;
    const TYPE_PAYMENT_SKINS        = 20;
    const TYPE_PAYMENT_TELEGRAM        = 21;
    const TYPE_PAYMENT_FUNPAY        = 22;

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
            [['payment_id', 'commission'], 'trim'],
            [['amount'], 'integer', 'min' => 1],
            [['created_at', 'completed_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'              => 'ID',
            'user_id'         => Yii::t('common', 'ID пользователя'),
            'payment_type'    => Yii::t('common', 'Метод оплаты'),
            'status'          => Yii::t('common', 'Статус'),
            'payment_id'      => Yii::t('common', 'ID платежа'),
            'amount'          => Yii::t('common', 'Сумма'),
            'amount_exchange' => Yii::t('common', 'Сумма в валюте'),
            'created_at'      => Yii::t('common', 'Дата операции'),
            'completed_at'    => Yii::t('common', 'Дата зачисления'),
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
//            self::TYPE_PAYMENT_CARD      => Yii::t('common', 'Оплата картой'),
//            self::TYPE_PAYMENT_SBP       => Yii::t('common', 'Оплата по СБП'),
//            self::TYPE_PAYMENT_TRON      => Yii::t('common', 'Оплата TRON'),
            self::TYPE_PAYMENT_TRC20     => Yii::t('common', 'Оплата TRC20'),
//            self::TYPE_PAYMENT_ERC20     => Yii::t('common', 'Оплата ERC20'),
//            self::TYPE_PAYMENT_YOOONEY     => Yii::t('common', 'Оплата ЮMoney'),
//            self::TYPE_PAYMENT_STEAM_PAY     => Yii::t('common', 'Оплата Steam Pay'),
//            self::TYPE_PAYMENT_VISA     => Yii::t('common', 'Оплата картой Visa'),
//            self::TYPE_PAYMENT_MIR     => Yii::t('common', 'Оплата картой МИР'),
//            self::TYPE_PAYMENT_PERFECT_MONEY     => Yii::t('common', 'Оплата Perfect Money'),
//            self::TYPE_PAYMENT_CARD_UA     => Yii::t('common', 'Оплата картой UA'),
//            self::TYPE_PAYMENT_CARD_KZT     => Yii::t('common', 'Оплата картой Казахстан'),
//            self::TYPE_PAYMENT_CARD_YM     => Yii::t('common', 'Оплата ЮMoney'),
            self::TYPE_PAYMENT_CARD_TINKOFF     => Yii::t('common', 'Оплата Тинькофф'),
            self::TYPE_PAYMENT_TON     => Yii::t('common', 'Оплата TON'),
            self::TYPE_PAYMENT_SKINS     => Yii::t('common', 'Оплата скинами'),
            self::TYPE_PAYMENT_TELEGRAM     => Yii::t('common', 'Оплата любой картой'),
            self::TYPE_PAYMENT_FUNPAY     => Yii::t('common', 'Оплата FunPay'),
        ];
    }

    /**
     * @return array
     */
    public static function getIconTypeList()
    {
        $icons = [
//            self::TYPE_PAYMENT_CARD      => '/images/payments/cards.svg',
//            self::TYPE_PAYMENT_SBP       => '/images/payments/sbp.svg',
//            self::TYPE_PAYMENT_YOOONEY     => '/images/payments/iomoney.png',
//            self::TYPE_PAYMENT_STEAM_PAY     => '/images/payments/scp_logo.png',
//            self::TYPE_PAYMENT_VISA     => '/images/payments/tron.svg',
//            self::TYPE_PAYMENT_MIR     => '/images/payments/tron.svg',
//            self::TYPE_PAYMENT_PERFECT_MONEY     => '/images/payments/Perfect_Money.png',
//            self::TYPE_PAYMENT_TRON      => '/images/payments/tron.svg',
//            self::TYPE_PAYMENT_TRC20     => '/images/payments/tether.svg',
//            self::TYPE_PAYMENT_ERC20     => '/images/payments/tether.svg',
//            self::TYPE_PAYMENT_CARD_UA     => '/images/payments/cards.svg',
//            self::TYPE_PAYMENT_CARD_KZT     => '/images/payments/cards.svg',
//            self::TYPE_PAYMENT_CARD_YM     => '/images/payments/iomoney.png',
//            self::TYPE_PAYMENT_CARD_TINKOFF     => '/images/payments/cards.svg',
        ];

        if (Yii::$app->settings->get('tinkoffpay_enabled')) {
            $icons[self::TYPE_PAYMENT_CARD_TINKOFF] = Yii::$app->settings->get('tinkoffpay_logo');
        }
        if (Yii::$app->settings->get('trc20_enabled')) {
            $icons[self::TYPE_PAYMENT_TRC20] = Yii::$app->settings->get('trc20_logo');
        }
        if (Yii::$app->settings->get('ton_enabled')) {
            $icons[self::TYPE_PAYMENT_TON] = Yii::$app->settings->get('ton_logo');
        }
        if (Yii::$app->settings->get('skinpay_enabled')) {
            $icons[self::TYPE_PAYMENT_SKINS] = Yii::$app->settings->get('skinpay_logo');
        }
        if (Yii::$app->settings->get('telegrampay_enabled')) {
            $icons[self::TYPE_PAYMENT_TELEGRAM] = Yii::$app->settings->get('telegrampay_logo');
        }
        if (Yii::$app->settings->get('funpay_enabled')) {
            $icons[self::TYPE_PAYMENT_FUNPAY] = Yii::$app->settings->get('funpay_logo');
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
//            self::TYPE_PAYMENT_CARD_UA     => '<div style="font-size: 14px">Украина</div>',
//            self::TYPE_PAYMENT_CARD_KZT     => '<div style="font-size: 14px">Казахстан</div>',
            self::TYPE_PAYMENT_TELEGRAM    => Yii::t('common', 'Любые карты'),
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
            self::TYPE_PAYMENT_TRC20         => [100, 100000],
            self::TYPE_PAYMENT_TON           => [100, 100000],
            //            self::TYPE_PAYMENT_ERC20         => [1000, 100000],
            self::TYPE_PAYMENT_YOOONEY       => [1000, 100000],
            self::TYPE_PAYMENT_STEAM_PAY     => [1000, 100000],
            //            self::TYPE_PAYMENT_VISA          => [1000, 100000],
            //            self::TYPE_PAYMENT_MIR           => [1000, 100000],
            self::TYPE_PAYMENT_PERFECT_MONEY => [1000, 100000],
            self::TYPE_PAYMENT_CARD_UA       => [1000, 100000],
            self::TYPE_PAYMENT_CARD_KZT      => [1000, 100000],
            self::TYPE_PAYMENT_CARD_YM       => [1000, 100000],
            self::TYPE_PAYMENT_CARD_TINKOFF  => [10, 100000],
            self::TYPE_PAYMENT_SKINS  => [50, 100000],
            self::TYPE_PAYMENT_TELEGRAM  => [50, 100000],
            self::TYPE_PAYMENT_FUNPAY  => [Yii::$app->settings->get('funpay_minSum'), 100000],
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
     * Atomically completes a deposit and runs its one-time side effects.
     *
     * The conditional UPDATE is the idempotency boundary. A callback, status
     * poll and cron job may all observe the provider's successful status at
     * the same time, but only one of them can change WAIT_CONFIRM to SUCCESS.
     *
     * @param bool $allowCanceled Whether an administrator may explicitly
     *                            complete a previously canceled deposit.
     */
    public function markSuccessful($allowCanceled = false)
    {
        if (empty($this->id)) {
            throw new \LogicException('A deposit must be persisted before it can be completed.');
        }

        $allowedStatuses = [self::STATUS_WAIT_CONFIRM];
        if ($allowCanceled) {
            $allowedStatuses[] = self::STATUS_CANCELED;
        }

        $transaction = static::getDb()->beginTransaction();
        try {
            $updated = static::updateAll(
                [
                    'status' => self::STATUS_SUCCESS,
                    'completed_at' => new Expression('CURRENT_TIMESTAMP'),
                ],
                ['id' => (int)$this->id, 'status' => $allowedStatuses]
            );

            if ($updated !== 1) {
                $transaction->rollBack();
                $this->refresh();
                return false;
            }

            $this->status = self::STATUS_SUCCESS;
            $this->completed_at = date('Y-m-d H:i:s');
            $bonusCreated = $this->createDepositBonus();
            if (!$bonusCreated) {
                // Profit::afterSave recalculates the balance when a bonus was
                // created. Deposits below the bonus threshold still need it.
                $this->user->getPersonalBalance()->recalculateBalance();
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            if ($transaction->isActive) {
                $transaction->rollBack();
            }
            throw $e;
        }

        // External delivery is deliberately outside the DB transaction. Only
        // the winner of the atomic transition reaches this point.
        try {
            $this->sendSuccessNotification();
        } catch (\Throwable $e) {
            Yii::error(sprintf(
                'Deposit #%d payment notification failed: %s',
                (int)$this->id,
                $e->getMessage()
            ), 'payment');
        }

        return true;
    }

    /**
     * Atomically cancels a deposit which is still awaiting confirmation.
     */
    public function markCanceled()
    {
        if (empty($this->id)) {
            return false;
        }

        $updated = static::updateAll(
            ['status' => self::STATUS_CANCELED],
            ['id' => (int)$this->id, 'status' => self::STATUS_WAIT_CONFIRM]
        );

        if ($updated === 1) {
            $this->status = self::STATUS_CANCELED;
            return true;
        }

        $this->refresh();
        return false;
    }

    /**
     * Pure bonus calculation shared by processing and regression tests.
     */
    public static function calculateBonusAmount($amount)
    {
        if ($amount >= 20000) {
            return (int)ceil($amount);
        }
        if ($amount >= 5000) {
            return (int)ceil($amount * 0.5);
        }
        if ($amount >= 2000) {
            return (int)ceil($amount * 0.3);
        }
        if ($amount >= 1500) {
            return (int)ceil($amount * 0.25);
        }
        if ($amount >= 1000) {
            return (int)ceil($amount * 0.2);
        }
        if ($amount >= 500) {
            return (int)ceil($amount * 0.15);
        }

        return 0;
    }

    private function createDepositBonus()
    {
        $bonus = self::calculateBonusAmount((int)$this->amount);
        if ($bonus <= 0) {
            return false;
        }

        $profit = new Profit();
        $profit->status = 1;
        $profit->type = Profit::TYPE_BONUS;
        $profit->amount = $bonus;
        $profit->deposit_id = $this->id;
        $profit->user_balance_id = $this->user->getPersonalBalance()->id;
        $profit->comment = Yii::t('common', 'Бонус при пополнении');
        $profit->created_at = date('Y-m-d H:i:s');
        if (!$profit->save(false)) {
            throw new \RuntimeException('Failed to save the deposit bonus.');
        }

        return true;
    }

    private function sendSuccessNotification()
    {
        if (!Yii::$app->has('telegramPayments')) {
            return;
        }

        $amountTotalSum = static::find()
            ->andWhere(['status' => self::STATUS_SUCCESS])
            ->sum('amount') ?? 0;
        $amountDaySum = static::find()
            ->andWhere(['between', 'completed_at', date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])
            ->andWhere(['status' => self::STATUS_SUCCESS])
            ->sum('amount') ?? 0;

        $user = $this->user;
        $amountStr = number_format($this->amount, 0, '.', ' ');
        $message = "💰️ <b>Пополнение баланса</b>" . PHP_EOL
            . 'Пользователь: ' . Html::encode($user->username) . PHP_EOL
            . 'SteamID: ' . Html::encode($user->steam_id) . PHP_EOL
            . "Сумма: {$amountStr} RUB";

        if (!empty($user->server)) {
            $message .= PHP_EOL . 'Сервер: ' . Html::encode($user->server->name);
        }
        if ($user->is_mirror_returned) {
            $message .= PHP_EOL . '<b>Игрок вернулся с зеркала</b>';
        }
        if ($user->is_mirror_registration) {
            $message .= PHP_EOL . '<b>Игрок пришел к нам зеркала</b>';
        }

        $depositsSum = static::find()
            ->andWhere(['user_id' => $user->id])
            ->andWhere(['status' => self::STATUS_SUCCESS])
            ->sum('amount') ?? 0;

        $paymentName = ArrayHelper::getValue(self::getTypeList(), $this->payment_type);
        if (!empty($paymentName)) {
            $message .= PHP_EOL . 'Метод оплаты: ' . Html::encode($paymentName);
        }

        $message .= PHP_EOL . PHP_EOL
            . 'Поступлений от игрока: ' . number_format($depositsSum, 0, '.', ' ') . ' RUB' . PHP_EOL
            . 'Всего за день: ' . number_format($amountDaySum, 0, '.', ' ') . ' RUB' . PHP_EOL
            . 'Всего за всегда: ' . number_format($amountTotalSum, 0, '.', ' ') . ' RUB';

        Yii::$app->telegramPayments->sendMessage($message);
    }

    public static function responseAdapter($response, $payment)
    {
        $data = json_decode($response, true);
        if (!is_array($data)) {
            return null;
        }

        $status = null;
        switch ($payment) {
            case 'tome':
                $event = $data['event'] ?? null;
                if ($event === 'payment.succeeded') {
                    $status = 'SUCCESS';
                }
                if ($event === 'payment.canceled') {
                    $status = 'CANCEL';
                }
                return [
                    'id' => $data['object']['id'] ?? null,
                    'status' => $status,
                ];
            case 'tinkoff':
                $providerStatus = $data['Status'] ?? null;
                if ($providerStatus === 'CONFIRMED') {
                    $status = 'SUCCESS';
                }
                if (in_array($providerStatus, ['PARTIAL_REVERSED', 'REVERSED', 'CANCELED', 'PARTIAL_REFUNDED', 'REFUNDED', 'REJECTED', 'DEADLINE_EXPIRED'], true)) {
                    $status = 'CANCEL';
                }
                return [
                    'id' => $data['PaymentId'] ?? null,
                    'status' => $status,
                ];
            case 'anypay':
                return [
                    'id' => $data['transaction_id'] ?? ($data['result']['transaction_id'] ?? null),
                    'status' => 'NO AVAILABLE',
                ];
            default:
                return null;
        }
    }

    public static function getExchange($currency)
    {
        $cacheKey = "Deposit_getExchanges_{$currency}";
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $curl = clone Yii::$app->curl
                    ->setHeader('Content-Type', 'application/json')
                    ->setRequestBody(null);

        if ($currency === 'RUB') {
            $exchangeTariff = $curl->get('https://api.binance.com/api/v3/ticker/price?symbol=USDTRUB');
        } else {
            $exchangeTariff = $curl->get('https://api.binance.com/api/v3/ticker/price?symbol=' . $currency . 'USDT');
        }
        $exchange = ArrayHelper::getValue(json_decode($exchangeTariff), 'price');

        Yii::$app->cache->set($cacheKey, $exchange, 60*60);
        return $exchange;
    }
}
