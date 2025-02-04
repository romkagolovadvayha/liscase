<?php

namespace common\components\payments;

use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class PaymentSkins
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
            'template' => 'payments/skins',
            'type' => Deposit::TYPE_PAYMENT_SKINS,
            'amount' => $deposit->amount,
            'trade_link' => Yii::$app->settings->get('skinpay_traide_link'),
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
