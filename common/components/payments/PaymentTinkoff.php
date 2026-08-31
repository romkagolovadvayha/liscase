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

        if ($model->status !== Deposit::STATUS_WAIT_CONFIRM) {
            return $model->status;
        }

        $request = $TBank->getState($model->payment_id);
        if (empty($request['Success'])) {
            return null;
        }

        if (!$this->matchesDeposit($request, $model)) {
            Yii::error(sprintf(
                'TBank status response does not match deposit #%d',
                (int)$model->id
            ), 'payment');
            return null;
        }

        $status = $request['Status'] ?? null;
        if (!is_string($status) || $status === '') {
            return null;
        }

        if ($status === 'CONFIRMED') {
            $model->markSuccessful();
        }

        if (in_array($status, ['PARTIAL_REVERSED', 'REVERSED', 'CANCELED', 'PARTIAL_REFUNDED', 'REFUNDED', 'REJECTED', 'DEADLINE_EXPIRED'], true)) {
            $model->markCanceled();
        }

        return $model->status;
    }

    public function debugCheck($depositId)
    {
        $terminalKey = Yii::$app->settings->get('tinkoffpay_terminalKey');
        $secretKey = Yii::$app->settings->get('tinkoffpay_secretKey');
        $TBank = new TBankMerchantAPI($terminalKey, $secretKey);
        $model = Deposit::findOne($depositId);
        if (!$model) {
            return null;
        }
        if ($model->status !== Deposit::STATUS_WAIT_CONFIRM) {
            return $model->status;
        }
        $request = $TBank->getState($model->payment_id);
        if (empty($request['Success']) || !$this->matchesDeposit($request, $model)) {
            return '';
        }
        return $request['Status'] ?? '';
    }

    private function matchesDeposit(array $response, Deposit $deposit): bool
    {
        $expectedAmount = (int)round(
            ((float)$deposit->amount + (float)$deposit->commission) * 100
        );

        return isset($response['PaymentId'], $response['OrderId'], $response['Amount'])
            && (string)$response['PaymentId'] === (string)$deposit->payment_id
            && (string)$response['OrderId'] === (string)$deposit->id
            && (int)$response['Amount'] === $expectedAmount;
    }
}
