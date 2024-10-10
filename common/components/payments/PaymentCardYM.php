<?php

namespace common\components\payments;

use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class PaymentCardYM
{

    public function create($amount)
    {
        $model = Deposit::createOperation(Yii::$app->user->id, $amount, Deposit::TYPE_PAYMENT_CARD_YM);
        $result = Yii::$app->anyPayApi->create($amount, 'ym', 'Пополнение баланса', $model->id, 'RUB', 'RUB');
        $model->payment_id = $result['result']['transaction_id'];
        $model->save(false);

        return $result['result']['payment_url'];
    }

    public function check($depositId)
    {
        $model = Deposit::findOne($depositId);
        if ($model->status !== Deposit::STATUS_WAIT_CONFIRM) {
            return $model->status;
        }
        $result = Yii::$app->anyPayApi->check($model->payment_id);
        if (empty($result['result']) || empty($result['result']['payments']) || empty($result['result']['payments'][$model->payment_id])) {
            return false;
        }
        $payment = $result['result']['payments'][$model->payment_id];
        if ($payment['status'] === 'paid') {
            $model->status = Deposit::STATUS_SUCCESS;
            $model->save(false);
            Deposit::bonus($model->user, $model->amount, $model->payment_type);
            $model->user->getPersonalBalance()->recalculateBalance();
        } elseif ($payment['status'] !== 'waiting' && $payment['status'] !== 'partially-paid') {
            $model->status = Deposit::STATUS_CANCELED;
            $model->save(false);
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
