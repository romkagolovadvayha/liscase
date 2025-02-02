<?php

namespace common\components\payments;

use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class PaymentTrc20
{

    /**
     * @param Deposit $deposit
     *
     * @return mixed
     */
    public function create($deposit)
    {
        $deposit->payment_id = Yii::$app->settings->get('trc20_wallet');
        $deposit->amount_exchange = round($deposit->amount / Deposit::getExchange('RUB'), 2);
        $deposit->save(false);

        return [
            'template' => 'payments/crypto',
            'type' => Deposit::TYPE_PAYMENT_TRC20,
            'exchange' => 'USDT',
            'network' => 'TRC20',
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
