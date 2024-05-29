<?php

namespace common\components\payments;

use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class PaymentCardRf
{

    public function create($amount)
    {
        $model = Deposit::createOperation(Yii::$app->user->id, $amount, Deposit::TYPE_PAYMENT_CARD);
        $result = Yii::$app->tomeApi->create($amount, 'card', 'Пополнение баланса', $model->id);
        $model->payment_id = $result['id'];
        $model->save(false);

        return $result['confirmation']['confirmation_url'];
    }

    public function check($depositId)
    {
        $model = Deposit::findOne($depositId);
        $result = Yii::$app->tomeApi->check($model->payment_id);
        if ($result['status'] === 'succeeded') {
            $model->status = Deposit::STATUS_SUCCESS;
            $model->save(false);
            Deposit::bonus($model->user, $model->amount);
            $model->user->getPersonalBalance()->recalculateBalance();
        } elseif ($result['status'] === 'canceled') {
            $model->status = Deposit::STATUS_CANCELED;
            $model->save(false);
        }

        return $model->status;
    }

}
