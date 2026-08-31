<?php

namespace common\components\payments;

use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class PaymentCardKZT
{

    /**
     * @param Deposit $deposit
     *
     * @return mixed
     */
    public function create($deposit)
    {
        $result = Yii::$app->anyPayApi->create($deposit->amount, 'card', 'Пополнение баланса', $deposit->id, 'RUB', 'KZT');
        $deposit->payment_id = $result['result']['transaction_id'];
        $deposit->save(false);

        return $result['result']['payment_url'];
    }

    public function check($depositId)
    {
        $model = Deposit::findOne($depositId);
        if (!$model) {
            return null;
        }
        if ($model->status !== Deposit::STATUS_WAIT_CONFIRM) {
            return $model->status;
        }
        $result = Yii::$app->anyPayApi->check($model->payment_id);
        if (empty($result['result']) || empty($result['result']['payments']) || empty($result['result']['payments'][$model->payment_id])) {
            if (empty($result['result']['payments'])) {
                $model->markCanceled();
            }
            return false;
        }
        $payment = $result['result']['payments'][$model->payment_id];
        if ($payment['status'] === 'paid') {
            $model->markSuccessful();
        } elseif ($payment['status'] !== 'waiting' && $payment['status'] !== 'partially-paid') {
            $model->markCanceled();
        }

        return $model->status;
    }

    public function debugCheck($depositId)
    {
        $model = Deposit::findOne($depositId);
        if ($model->status !== Deposit::STATUS_WAIT_CONFIRM) {
            return $model->status;
        }
        $result = Yii::$app->anyPayApi->check($model->payment_id);
        if (empty($result['result']) || empty($result['result']['payments']) || empty($result['result']['payments'][$model->payment_id])) {
            return 'not result';
        }
        return $result['result']['payments'][$model->payment_id]['status'];
    }

}
