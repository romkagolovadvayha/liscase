<?php

namespace common\components\payments;

use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class PaymentTron
{

    public function create($amount)
    {
        $model = Deposit::createOperation(Yii::$app->user->id, $amount, Deposit::TYPE_PAYMENT_TRON);
        $result = Yii::$app->freeKassaApi->create($amount, 39, null, $model->id);
        if (!empty($result['error'])) {
            throw new \Exception($result['error'], 414);
        }
        $model->payment_id = $result['orderId'];
        $model->save(false);

        return $result['location'];
    }

    public function check($depositId)
    {
        $model = Deposit::findOne($depositId);
        $result = Yii::$app->freeKassaApi->check($model->payment_id);
        if ($result['orders'][0]['status'] === 1) {
            $model->status = Deposit::STATUS_SUCCESS;
            $model->save(false);
            $model->user->getPersonalBalance()->recalculateBalance();
        } elseif ($result['orders'][0]['status'] === 8 || $result['orders'][0]['status'] === 9) {
            $model->status = Deposit::STATUS_CANCELED;
            $model->save(false);
        }

        return $model->status;
    }

}
