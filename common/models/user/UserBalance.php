<?php

namespace common\models\user;

use common\components\helpers\CurrencyHelper;
use common\models\invoice\Deposit;
use common\models\invoice\Invoice;
use WebSocket\Client;
use Yii;
use common\models\profit\Profit;
use yii\base\BaseObject;

/**
 * This is the model class for table "user_balance".
 *
 * @property int      $id
 * @property int      $user_id
 * @property int      $type
 * @property float    $balance
 * @property string   $created_at
 *
 * @property User      $user
 * @property Profit[]  $profits
 * @property Invoice[] $invoices
 * @property float     $balanceCeil
 */
class UserBalance extends \common\components\base\ActiveRecord
{
    const TYPE_PERSONAL = 1;
    const TYPE_SKINS = 2;

    /**
     * @return array
     */
    public static function getTypeList()
    {
        return [
            self::TYPE_PERSONAL => Yii::t('common', 'Лицевой счет'),
            self::TYPE_SKINS => Yii::t('common', 'Скины'),
        ];
    }

    /**
     * @return array
     */
    public static function getCurrencyLabelList()
    {
        return [
            self::TYPE_PERSONAL => 'RUB',
            self::TYPE_SKINS => 'RUB',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_balance';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_id', 'type'], 'required'], [['user_id', 'type'], 'integer'], [['balance'], 'number'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id'         => Yii::t('common', 'ID'),
            'user_id' => Yii::t('common', 'Пользователь'),
            'type'       => Yii::t('common', 'Тип баланса'),
            'balance' => Yii::t('common', 'Баланс'),
            'created_at' => Yii::t('common', 'Дата создания'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getProfits()
    {
        return $this->hasMany(Profit::class, ['user_balance_id' => 'id'])->andWhere(['status' => 1]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getInvoices()
    {
        return $this->hasMany(Invoice::class, ['user_id' => 'user_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getDeposits()
    {
        return $this->hasMany(Deposit::class, ['user_id' => 'user_id']);
    }

    /**
     *
     * @return string
     */
    public static function getCurrency()
    {
        return CurrencyHelper::default();
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * @param int $userId
     * @param int $type
     *
     * @return static|null
     */
    public static function getBalance($userId, $type)
    {
        return self::findOne(['user_id' => $userId, 'type' => $type]);
    }

    public function getBalanceCeil() {
        return ceil($this->balance);
    }

    /**
     * @param int $userId
     * @param int $type
     *
     * @return static|false
     */
    public static function getModel($userId, $type)
    {
        $model = self::getBalance($userId, $type);
        if (!empty($model)) {
            return $model;
        }

        $model = new self();

        $model->user_id = $userId;
        $model->type    = $type;
        $model->balance = 0;
        $model->save();

        return $model;
    }

    public function recalculateBalance()
    {
        $oldBalance = $this->balance;
        $balance = (float)$this->getProfits()->sum('amount');
        if ($this->type === self::TYPE_PERSONAL) {
            $invoices = (float)$this->getInvoices()->sum('amount');
            $deposits = (float)$this->getDeposits()->andWhere(['status' => Deposit::STATUS_SUCCESS])->sum('amount');
            $this->balance = ceil($balance + $deposits - $invoices);
            $this->save(false);
        }
        if ($this->type === self::TYPE_SKINS) {
            $payouts = UserPayoutSkins::find()
                                ->andWhere(['IN', 'status', [UserPayoutSkins::STATUS_NEW, UserPayoutSkins::STATUS_WAIT, UserPayoutSkins::STATUS_SUCCESS]])
                                ->andWhere(['user_id' => $this->user->id])
                                ->sum('amount');
            $personalBalance = $this->user->getPersonalBalance();
            $transfers = Profit::find()
                                ->andWhere(['IN', 'type', [Profit::TYPE_TRANSFER_SKINS]])
                                ->andWhere(['user_balance_id' => $personalBalance->id])
                                ->sum('amount');
            $this->balance = ceil($balance - $payouts - $transfers);
            $this->save(false);
        }

        try {
            if ($this->type === self::TYPE_PERSONAL && $oldBalance != $this->balance) {
                // Сохраняем в кеш для отправки через WebSocket таймер
                $cacheKey = 'ws_balance_update_' . $this->user_id;
                Yii::$app->cache->set($cacheKey, [
                    'action' => 'updatedBalance',
                    'code' => 200,
                    'user_id' => $this->user_id,
                    'balanceStr' => $this->getBalanceFormat(),
                    'balance' => $this->balanceCeil,
                    'timestamp' => time(),
                ], 30);
            }
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('UserBalance recalculateBalance: ' . $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage());
        }
    }

    /**
     * @return string
     */
    public function getBalanceFormat()
    {
        return number_format($this->balanceCeil, 0, '.', ' ');
    }
}
