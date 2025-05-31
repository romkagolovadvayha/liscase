<?php

namespace common\components\payments;

use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class PaymentFunPay
{

    /**
     * @param Deposit $deposit
     *
     * @return mixed
     */
    public function create($deposit)
    {
        $deposit->payment_id = Yii::$app->settings->get('funpay_baseUrl');
        $deposit->save(false);

        return [
            'template' => 'payments/funpay',
            'type' => Deposit::TYPE_PAYMENT_FUNPAY,
            'amount' => $deposit->amount,
            'url' => Yii::$app->settings->get('funpay_baseUrl'),
            'deadline' => 30 * 60,
        ];
    }

    public function check($depositId)
    {
        return Deposit::STATUS_CANCELED;
    }

    public function debugCheck($depositId)
    {
        return Deposit::STATUS_CANCELED;
    }

}
