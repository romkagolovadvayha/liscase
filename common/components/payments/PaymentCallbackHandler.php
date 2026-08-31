<?php

namespace common\components\payments;

use common\components\merchant\TBankMerchantAPI;
use common\models\invoice\Deposit;
use Yii;
use yii\web\Response;

/**
 * Обработка POST-callback от платёжных систем (одинаково для API и legacy frontend).
 */
final class PaymentCallbackHandler
{
    /**
     * @return array|string Provider-specific acknowledgement body.
     */
    public static function handle(string $payment)
    {
        $isTinkoff = $payment === 'tinkoff';
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = $isTinkoff
            ? Response::FORMAT_RAW
            : Response::FORMAT_JSON;

        $paymentTypes = self::paymentTypes($payment);
        if ($paymentTypes === []) {
            Yii::$app->response->statusCode = 404;
            return $isTinkoff
                ? 'UNKNOWN PAYMENT PROVIDER'
                : ['code' => 404, 'message' => 'Unknown payment provider'];
        }

        try {
            $rawBody = Yii::$app->request->getRawBody();
            if ($isTinkoff && !self::isValidTinkoffNotification($rawBody)) {
                Yii::$app->response->statusCode = 403;
                Yii::warning('Rejected a TBank callback with an invalid signature', 'payment');
                return 'INVALID TOKEN';
            }

            $response = Deposit::responseAdapter($rawBody, $payment);

            if (empty($response) || empty($response['id'])) {
                Yii::$app->response->statusCode = 400;
                return $isTinkoff
                    ? 'INVALID CALLBACK DATA'
                    : ['code' => 400, 'message' => 'Invalid callback data'];
            }

            $deposit = Deposit::find()
                ->where(['status' => Deposit::STATUS_WAIT_CONFIRM])
                ->andWhere(['payment_id' => $response['id']])
                ->andWhere(['payment_type' => $paymentTypes])
                ->one();

            if (empty($deposit)) {
                // Repeated notifications for an already completed deposit must
                // be acknowledged so the provider stops retrying them.
                return self::successResponse($isTinkoff, 'Deposit not found or already processed');
            }

            if ((int)$deposit->payment_type === Deposit::TYPE_PAYMENT_CARD_TINKOFF) {
                $status = $deposit->check();
            } else {
                $paymentApi = PaymentApi::getInstance($deposit->payment_type);
                $status = $paymentApi->check($deposit->id);
            }

            if ($status === null || $status === false) {
                Yii::$app->response->statusCode = 503;
                return $isTinkoff
                    ? 'TEMPORARY ERROR'
                    : ['code' => 503, 'message' => 'Payment status check failed'];
            }

            return self::successResponse($isTinkoff, 'Callback processed');
        } catch (\Throwable $e) {
            Yii::$app->response->statusCode = 500;
            Yii::error('Payment callback error: ' . $e->getMessage(), 'payment');
            return $isTinkoff
                ? 'INTERNAL ERROR'
                : ['code' => 500, 'message' => 'Callback processing failed'];
        }
    }

    private static function successResponse($isTinkoff, $message)
    {
        return $isTinkoff ? 'OK' : ['code' => 200, 'message' => $message];
    }

    private static function isValidTinkoffNotification($rawBody)
    {
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return false;
        }

        $terminalKey = Yii::$app->settings->get('tinkoffpay_terminalKey');
        $secretKey = Yii::$app->settings->get('tinkoffpay_secretKey');
        if (empty($terminalKey) || empty($secretKey)) {
            return false;
        }

        return (new TBankMerchantAPI($terminalKey, $secretKey))
            ->isValidNotification($payload);
    }

    private static function paymentTypes($payment)
    {
        switch ($payment) {
            case 'tinkoff':
                return [Deposit::TYPE_PAYMENT_CARD_TINKOFF];
            case 'tome':
                return [Deposit::TYPE_PAYMENT_CARD, Deposit::TYPE_PAYMENT_SBP];
            case 'anypay':
                return [
                    Deposit::TYPE_PAYMENT_PERFECT_MONEY,
                    Deposit::TYPE_PAYMENT_CARD_UA,
                    Deposit::TYPE_PAYMENT_CARD_KZT,
                    Deposit::TYPE_PAYMENT_CARD_YM,
                ];
            default:
                return [];
        }
    }

    /**
     * Абсолютный URL webhook для платёжки (публичный хост API, без завершающего /).
     *
     * @see params.apiPublicUrl
     */
    public static function callbackUrlFor(string $paymentSlug): string
    {
        $base = rtrim((string)(Yii::$app->params['apiPublicUrl'] ?? ''), '/');
        if ($base === '') {
            return '';
        }

        return $base . '/v1/payment/callback/' . $paymentSlug;
    }
}
