<?php

namespace frontend\forms\market;

use common\components\helpers\EmailHelper;
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
    public $email;

    public function rules(): array
    {
        return ArrayHelper::merge([
            [['payment_id', 'amount'], 'required'],
            ['email', 'required', 'when' => function($model) {
                $user = Yii::$app->user->identity;
                return $user && !$user->is_email;
            }],
            [['payment_id', 'email'], 'trim'],
            [['payment_id', 'amount', 'confirm'], 'integer'],
            ['payment_id', 'validatePaymentId'],
            ['email', 'validateEmail'],
        ], parent::rules());
    }

    public function attributeLabels(): array
    {
        return [
            'amount'    => Yii::t('common', 'Сумма пополнения'),
            'payment_id'    => Yii::t('common', 'Метод оплаты'),
            'confirm' => Yii::t('common', 'Условия соглашения'),
            'email' => Yii::t('common', 'E-mail адрес'),
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

    /**
     * @param $attribute
     */
    public function validateEmail($attribute)
    {
        // Проверяем валидность email только если он заполнен
        if (!empty($this->email) && !EmailHelper::isValid($this->email)) {
            $this->addError($attribute, Yii::t('common', 'E-mail адрес введен неверно.'));
        }
    }

    public function init()
    {
        parent::init();
        /** @var User $user */
        $user = Yii::$app->user->identity;
        if ($user->is_email) {
            $this->email = $user->email;
        }
        $this->amount = 100;
        $this->confirm = true;
        $this->payment_id = array_keys(Deposit::getIconTypeList())[0];
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
        /** @var User $user */
        $user = Yii::$app->user->identity;

        $user->is_email = true;
        $user->email = $this->email;
        $user->save();

        $paymentApi = PaymentApi::getInstance($this->payment_id);
        $deposit = Deposit::createOperation($user->id, $this->amount, $this->payment_id);
        try {
            return $paymentApi->create($deposit);
        } catch (\Exception $ex) {
            $deposit->markCanceled();
            Yii::$app->telegramChats->sendMessage('PaymentForm: ' . $ex->getMessage());
            Yii::$app->session->addFlash('danger', Yii::t('common', 'Платежная система не доступна в данный момент, попробуйте другой метод оплаты.'));
        }

        return null;
    }

}
