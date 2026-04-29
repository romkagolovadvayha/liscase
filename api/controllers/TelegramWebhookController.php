<?php

namespace api\controllers;

use common\components\telegram\foreignSystem\PersonalBotSystem;
use common\components\telegram\foreignSystem\RustotekaBotSystem;
use common\components\telegram\foreignSystem\SupportAlertBotSystem;
use common\components\telegram\TelegramWebhookProcessor;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Вебхуки Telegram Bot API (раньше — модуль frontend/webhook).
 *
 * У одного токена Bot API только один webhook. Если personal и support в настройках — один токен,
 * регистрируйте {@see actionUnified} (см. console telegram/set-webhooks).
 */
class TelegramWebhookController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionPersonal(string $token): string
    {
        return $this->dispatch(new PersonalBotSystem(), $token);
    }

    /**
     * Один бот на personal + support: команды пользователя и callback модерации ({@see SupportAlertBotSystem}).
     */
    public function actionUnified(string $token): string
    {
        $system = $this->resolveSystemForUrlToken($token);

        return $this->dispatch($system, $token);
    }

    public function actionRustoteka(string $token): string
    {
        return $this->dispatch(new RustotekaBotSystem(), $token);
    }

    public function actionSupport(string $token): string
    {
        return $this->dispatch(new SupportAlertBotSystem(), $token);
    }

    /**
     * @throws NotFoundHttpException
     */
    private function resolveSystemForUrlToken(string $token): object
    {
        $personal = (string) Yii::$app->settings->get('tgbot_botToken');
        $support = (string) Yii::$app->settings->get('tgbotSupportAlert_token');
        if ($token === '' || ($token !== $personal && $token !== $support)) {
            throw new NotFoundHttpException();
        }
        if ($personal !== '' && $personal === $support && $token === $personal) {
            return new SupportAlertBotSystem();
        }
        if ($support !== '' && $token === $support) {
            return new SupportAlertBotSystem();
        }
        if ($personal !== '' && $token === $personal) {
            return new PersonalBotSystem();
        }

        throw new NotFoundHttpException();
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
