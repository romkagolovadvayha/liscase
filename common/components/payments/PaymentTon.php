<?php

namespace common\components\payments;

use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class PaymentTon
{

    /**
     * @param Deposit $deposit
     *
     * @return mixed
     */
    public function create($deposit)
    {
        $deposit->payment_id = Yii::$app->settings->get('ton_wallet');
        $commission = !empty(Yii::$app->settings->get('ton_percent')) ? round($deposit->amount * (Yii::$app->settings->get('ton_percent') / 100)) : 0;
        $amountExchangeUSDT = round(($deposit->amount + $commission) / Deposit::getExchange('RUB'), 2);
        $deposit->commission = $commission;
        $deposit->amount_exchange = round($amountExchangeUSDT / Deposit::getExchange('TON'), 2);
        $deposit->save(false);

        return [
            'template' => 'payments/crypto',
            'type' => Deposit::TYPE_PAYMENT_TON,
            'exchange' => 'TON',
            'network' => 'TON',
            'amount' => $deposit->amount,
            'amount_exchange' => $deposit->amount_exchange,
            'wallet' => $deposit->payment_id,
            'deadline' => 10 * 60,
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
