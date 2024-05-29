<?php

namespace frontend\forms\market;

use common\components\payments\PaymentApi;
use common\models\invoice\Deposit;
use common\models\user\User;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

class PaymentForm extends Model
{
    public $amount;
    public $payment_id;
    public $confirm;

    public function rules(): array
    {
        return ArrayHelper::merge([
            [['payment_id', 'amount'], 'required'],
            [['payment_id'], 'trim'],
            [['payment_id', 'amount', 'confirm'], 'integer'],
            ['payment_id', 'validatePaymentId'],
        ], parent::rules());
    }

    public function attributeLabels(): array
    {
        return [
            'amount'    => Yii::t('common', 'Сумма пополнения'),
            'payment_id'    => Yii::t('common', 'Метод оплаты'),
            'confirm' => Yii::t('common', 'Условия соглашения'),
        ];
    }

    /**
     * @param $attribute
     */
    public function validatePaymentId($attribute)
    {
        if (!in_array($this->payment_id, array_keys(Deposit::getTypeList()))) {
            $this->addError($attribute, Yii::t('common', 'Метод оплаты не найден!'));
        }
        $limits = Deposit::getLimits();
        if (!empty($limits[$this->payment_id])) {
            $minLimit = $limits[$this->payment_id][0];
            $maxLimit = $limits[$this->payment_id][1];
            if ($this->amount < $minLimit) {
                $this->addError($attribute, Yii::t('common', 'Минимальная сумма депозита для выбранного метода {PARAMS_SUM_LIMIT} RUB', [
                    'PARAMS_SUM_LIMIT' => $minLimit
                ]));
            }
            if ($this->amount > $maxLimit) {
                $this->addError($attribute, Yii::t('common', 'Максимальная сумма депозита для выбранного метода {PARAMS_SUM_LIMIT} RUB', [
                    'PARAMS_SUM_LIMIT' => $maxLimit
                ]));
            }
        }
    }

    public function init()
    {
        parent::init();
        $this->amount = 100;
        $this->confirm = 1;
        $this->payment_id = array_keys(Deposit::getTypeList())[0];
    }

    public function createOperation()
    {
        if (!$this->validate()) {
            return false;
        }
        if (!$this->confirm) {
            $this->addError('confirm', Yii::t('common', 'Необходимо согласиться с условиями пользовательского соглашения'));
            return false;
        }

        $paymentApi = PaymentApi::getInstance($this->payment_id);
        $urlConfirm = $paymentApi->create($this->amount);

        return $urlConfirm;
    }

}
