<?php

namespace api\controllers;

use common\components\max\MaxSupportSettings;
use common\components\max\MaxSupportWebhookProcessor;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Webhook MAX Bot API для чата поддержки.
 */
class MaxWebhookController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionSupport(): string
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'text/plain; charset=UTF-8');

        $settings = new MaxSupportSettings();
        $configuredSecret = $settings->webhookSecret();
        $receivedSecret = (string)Yii::$app->request->headers->get('X-Max-Bot-Api-Secret', '');
        if ($configuredSecret === '' || !hash_equals($configuredSecret, $receivedSecret)) {
            throw new ForbiddenHttpException('Invalid MAX webhook secret.');
        }

        // При выключенной интеграции подтверждаем событие, но ничего не обрабатываем,
        // чтобы MAX не повторял доставку уже зарегистрированного webhook.
        if (!$settings->isEnabled()) {
            return '';
        }

        $raw = Yii::$app->request->getRawBody();
        $update = json_decode((string)$raw, true);
        if (!is_array($update)) {
            throw new BadRequestHttpException('Invalid JSON payload.');
        }

        (new MaxSupportWebhookProcessor())->process($update);

        return '';
    }
}
