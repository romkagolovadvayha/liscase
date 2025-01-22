<?php

namespace common\components\payments;

use common\components\merchant\TBankMerchantAPI;
use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class PaymentTinkoff
{
    /**
     * @param Deposit $deposit
     *
     * @return mixed
     */
    public function create($deposit)
    {
        $terminalKey = '1737463692019';
        $secretKey = 'h3ba1mc*oVHGwj6R';
        $TBank = new TBankMerchantAPI($terminalKey, $secretKey);
        $request = $TBank->create($deposit->amount, 'sbp', 'Донат на игровой сервер', $deposit->id, null, null, $deposit->user->email);
        if (!$request['Success']) {
            return null;
        }
        $deposit->payment_id = $request["PaymentId"];
        $deposit->save(false);

        return $request['PaymentURL'];
    }

    public function check($depositId)
    {
        $terminalKey = '1737463692019';
        $secretKey = 'h3ba1mc*oVHGwj6R';
        $TBank = new TBankMerchantAPI($terminalKey, $secretKey);
        $request = $TBank->check($depositId);
        $model = Deposit::findOne($depositId);
        if (!$request['Success']) {
            return null;
        }

        if ($model->status !== Deposit::STATUS_WAIT_CONFIRM) {
            return $model->status;
        }

        if ($request['Payments'][0]['Status'] === 'CONFIRMED') {
            $model->status = Deposit::STATUS_SUCCESS;
            $model->save(false);
            Deposit::bonus($model->user, $model->amount, $model->payment_type);
            $model->user->getPersonalBalance()->recalculateBalance();
        }

        if (in_array($request['Payments'][0]['Status'], ['PARTIAL_REVERSED', 'REVERSED', 'CANCELED', 'PARTIAL_REFUNDED', 'REFUNDED', 'REJECTED', 'DEADLINE_EXPIRED'])) {
            $model->status = Deposit::STATUS_CANCELED;
            $model->save(false);
        }

        return $model->status;
    }

    public function debugCheck($depositId)
    {
        $terminalKey = '1737463692019';
        $secretKey = 'h3ba1mc*oVHGwj6R';
        $TBank = new TBankMerchantAPI($terminalKey, $secretKey);
        $model = Deposit::findOne($depositId);
        if ($model->status !== Deposit::STATUS_WAIT_CONFIRM) {
            return $model->status;
        }
        $request = $TBank->check($depositId);

        return $request['Payments'][0]['Status'];
    }

}
