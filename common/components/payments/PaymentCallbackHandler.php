<?php

namespace common\components\payments;

use common\models\invoice\Deposit;
use Yii;
use yii\web\Response;

/**
 * Обработка POST-callback от платёжных систем (одинаково для API и legacy frontend).
 */
final class PaymentCallbackHandler
{
    /**
     * @return array JSON-тело ответа (уже выставлены statusCode и format у response)
     */
    public static function handle(string $payment): array
    {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = Response::FORMAT_JSON;

        if ($payment !== 'tinkoff' && Yii::$app->has('telegramChats')) {
            Yii::$app->telegramChats->sendMessage(Yii::$app->request->getRawBody());
        }

        try {
            $response = Deposit::responseAdapter(Yii::$app->request->getRawBody(), $payment);

            if (empty($response) || empty($response['id'])) {
                return ['code' => 200, 'message' => 'Invalid callback data'];
            }

            $deposit = Deposit::find()
                ->where(['status' => Deposit::STATUS_WAIT_CONFIRM])
                ->andWhere(['payment_id' => $response['id']])
                ->one();

            if (empty($deposit)) {
                return ['code' => 200, 'message' => 'Deposit not found or already processed'];
            }

            if ((int)$deposit->payment_type === Deposit::TYPE_PAYMENT_CARD_TINKOFF) {
                $deposit->check();
            } else {
                $paymentApi = PaymentApi::getInstance($deposit->payment_type);
                $paymentApi->check($deposit->id);
            }

            return ['code' => 200, 'message' => 'Callback processed'];
        } catch (\Throwable $e) {
            Yii::error('Payment callback error: ' . $e->getMessage());
            return ['code' => 500, 'message' => 'Callback processing failed'];
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
