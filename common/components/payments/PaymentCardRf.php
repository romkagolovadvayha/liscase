<?php

namespace common\components\payments;

use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class PaymentCardRf
{

    /**
     * @param Deposit $deposit
     *
     * @return mixed
     */
    public function create($deposit)
    {
        $result = Yii::$app->tomeApi->create($deposit->amount, 'card', 'Пополнение баланса', $deposit->id);
        $deposit->payment_id = $result['id'];
        $deposit->save(false);

        return $result['confirmation']['confirmation_url'];
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
        $result = Yii::$app->tomeApi->check($model->payment_id);
        if (empty($result['status'])) {
            return false;
        }
        if ($result['status'] === 'succeeded') {
            $model->markSuccessful();
        } elseif ($result['status'] === 'canceled') {
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
        $result = Yii::$app->tomeApi->check($model->payment_id);
        if (empty($result['status'])) {
            return 'not result';
        }

        return $result['status'];
    }

}
