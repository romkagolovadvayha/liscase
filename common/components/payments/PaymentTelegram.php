<?php

namespace common\components\payments;

use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class PaymentTelegram
{

    /**
     * @param Deposit $deposit
     *
     * @return mixed
     */
    public function create($deposit)
    {
        $deposit->amount_exchange = $deposit->amount;
        $deposit->save(false);

        return [
            'template' => 'payments/telegram',
            'type' => Deposit::TYPE_PAYMENT_TELEGRAM,
            'amount' => $deposit->amount,
            'username' => Yii::$app->settings->get('telegrampay_login'),
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
