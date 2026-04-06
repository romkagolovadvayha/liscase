<?php

namespace api\controllers;

use common\components\telegram\foreignSystem\PersonalBotSystem;
use common\components\telegram\foreignSystem\RustotekaBotSystem;
use common\components\telegram\TelegramWebhookProcessor;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Вебхуки Telegram Bot API (раньше — модуль frontend/webhook).
 */
class TelegramWebhookController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionPersonal(string $token): string
    {
        return $this->dispatch(new PersonalBotSystem(), $token);
    }

    public function actionRustoteka(string $token): string
    {
        return $this->dispatch(new RustotekaBotSystem(), $token);
    }

    private function dispatch(object $system, string $token): string
    {
        $raw = Yii::$app->request->getRawBody();
        if ($raw === null || $raw === '') {
            $raw = (string) file_get_contents('php://input');
        }

        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'text/plain; charset=UTF-8');

        if (!TelegramWebhookProcessor::tokenMatches($system, $token)) {
            throw new NotFoundHttpException();
        }

        TelegramWebhookProcessor::process($system, $raw);

        return '';
    }
}
