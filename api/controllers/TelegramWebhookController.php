<?php

namespace api\controllers;

use common\components\telegram\foreignSystem\PersonalBotSystem;
use common\components\telegram\foreignSystem\RustotekaBotSystem;
use common\components\telegram\foreignSystem\SupportAlertBotSystem;
use common\components\telegram\TelegramWebhookProcessor;
use Yii;
use yii\helpers\Html;
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
    private const TELEGRAM_CHATS_WEBHOOK_CHUNK = 3500;

    private const TELEGRAM_CHATS_WEBHOOK_MAX_PARTS = 25;

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

        $this->forwardWebhookPayloadToTelegramChats($raw);

        TelegramWebhookProcessor::process($system, $raw);

        return '';
    }

    /**
     * Дублирует сырое тело входящего update в alert-чат (tgbotAlert_*), для отладки / мониторинга.
     */
    private function forwardWebhookPayloadToTelegramChats(string $raw): void
    {
        if (!Yii::$app->has('telegramChats')) {
            return;
        }

        $plain = $raw === '' ? '[telegram webhook] empty body' : $raw;
        $escaped = Html::encode($plain);
        $chunk = self::TELEGRAM_CHATS_WEBHOOK_CHUNK;
        $len = mb_strlen($escaped, 'UTF-8');
        if ($len <= $chunk) {
            $parts = [$escaped];
        } else {
            $parts = [];
            for ($i = 0; $i < $len && count($parts) < self::TELEGRAM_CHATS_WEBHOOK_MAX_PARTS; $i += $chunk) {
                $parts[] = mb_substr($escaped, $i, $chunk, 'UTF-8');
            }
            if ($len > $chunk * count($parts)) {
                $parts[count($parts) - 1] .= "\n… (truncated, payload too long)";
            }
        }

        $total = count($parts);
        foreach ($parts as $idx => $part) {
            $prefix = $total > 1
                ? '[telegram webhook ' . ($idx + 1) . '/' . $total . "]\n"
                : '[telegram webhook] ';
            $message = '<pre>' . $prefix . $part . '</pre>';
            try {
                Yii::$app->telegramChats->sendMessage($message);
            } catch (\Throwable $e) {
                Yii::warning('TelegramWebhookController telegramChats: ' . $e->getMessage(), __METHOD__);
                break;
            }
        }
    }
}
