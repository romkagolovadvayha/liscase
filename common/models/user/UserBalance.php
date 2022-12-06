<?php

namespace common\models\user;

use common\components\helpers\CurrencyHelper;
use common\models\invoice\Invoice;
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

    /**
     * @return array
     */
    public static function getTypeList()
    {
        return [
            self::TYPE_PERSONAL => Yii::t('common', 'Лицевой счет'),
        ];
    }

    /**
     * @return array
     */
    public static function getCurrencyLabelList()
    {
        return [
            self::TYPE_PERSONAL => 'RUB',
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
        $balance = (float)$this->getProfits()->sum('amount');
        $invoices = (float)$this->getInvoices()->sum('amount');

        $this->balance = $balance - $invoices;
        $this->save(false);
    }

    /**
     * @return string
     */
    public function getBalanceFormat()
    {
        return number_format($this->balanceCeil, 0, '.', ' ');
    }
}
