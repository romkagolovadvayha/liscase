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
        $terminalKey = Yii::$app->settings->get('tinkoffpay_terminalKey');
        $secretKey = Yii::$app->settings->get('tinkoffpay_secretKey');
        $commission = !empty(Yii::$app->settings->get('tinkoffpay_percent')) ? round($deposit->amount * (Yii::$app->settings->get('tinkoffpay_percent') / 100)) : 0;
        $TBank = new TBankMerchantAPI($terminalKey, $secretKey);
        $request = $TBank->create($deposit->amount + $commission, 'sbp', 'Донат на игровой сервер', $deposit->id, null, null, $deposit->user->email);
        if (empty($request['Success']) || empty($request['PaymentId']) || empty($request['PaymentURL'])) {
            Yii::warning(sprintf(
                'TBank payment creation failed: code=%s message=%s',
                (string)($request['ErrorCode'] ?? 'unknown'),
                (string)($request['Message'] ?? 'unknown')
            ), 'payment');
            return null;
        }
        $deposit->commission = $commission;
        $deposit->payment_id = $request["PaymentId"];
        $deposit->save(false);

        return ['paymentURL' => $request['PaymentURL']];
    }

    public function check($depositId)
    {
        $terminalKey = Yii::$app->settings->get('tinkoffpay_terminalKey');
        $secretKey = Yii::$app->settings->get('tinkoffpay_secretKey');
        $TBank = new TBankMerchantAPI($terminalKey, $secretKey);
        $model = Deposit::findOne($depositId);
        if (!$model) {
            return null;
        }

        $request = $TBank->check($depositId);
        if (empty($request['Success'])) {
            return null;
        }

        if ($model->status !== Deposit::STATUS_WAIT_CONFIRM) {
            return $model->status;
        }

        $status = $request['Payments'][0]['Status'] ?? null;
        if (!is_string($status) || $status === '') {
            return null;
        }

        if ($status === 'CONFIRMED') {
            $model->status = Deposit::STATUS_SUCCESS;
            $model->save(false);
            Deposit::bonus($model->user, $model->amount, $model->payment_type);
            $model->user->getPersonalBalance()->recalculateBalance();
        }

        if (in_array($status, ['PARTIAL_REVERSED', 'REVERSED', 'CANCELED', 'PARTIAL_REFUNDED', 'REFUNDED', 'REJECTED', 'DEADLINE_EXPIRED'], true)) {
            $model->status = Deposit::STATUS_CANCELED;
            $model->save(false);
        }

        return $model->status;
    }

    public function debugCheck($depositId)
    {
        $terminalKey = Yii::$app->settings->get('tinkoffpay_terminalKey');
        $secretKey = Yii::$app->settings->get('tinkoffpay_secretKey');
        $TBank = new TBankMerchantAPI($terminalKey, $secretKey);
        $model = Deposit::findOne($depositId);
        if ($model->status !== Deposit::STATUS_WAIT_CONFIRM) {
            return $model->status;
        }
        $request = $TBank->check($depositId);
        if (empty($request['Payments'])) {
            return '';
        }
        return $request['Payments'][0]['Status'] ?? '';
    }

}
