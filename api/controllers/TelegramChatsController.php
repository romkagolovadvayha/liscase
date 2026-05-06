<?php

namespace api\controllers;

use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

class TelegramChatsController extends Controller
{
    private const TELEGRAM_MESSAGE_LIMIT = 3500;

    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'ingest' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                ],
            ],
        ]);
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        Yii::$app->response->format = Response::FORMAT_JSON;

        return true;
    }

    public function actionIngest()
    {
        $request = Yii::$app->request;

        $payload = [
            'method' => $request->method,
            'url' => $request->absoluteUrl,
            'pathInfo' => $request->pathInfo,
            'queryString' => $request->queryString,
            'userIP' => $request->userIP,
            'userHost' => $request->userHost,
            'userAgent' => $request->userAgent,
            'headers' => $request->headers->toArray(),
            'get' => $request->get(),
            'post' => $request->post(),
            'bodyParams' => $request->bodyParams,
            'rawBody' => $request->rawBody,
            'files' => $_FILES,
            'cookies' => $_COOKIE,
            'server' => $_SERVER,
        ];

        $message = "[telegram-chats ingest]\n" . json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if ($message === '[telegram-chats ingest]' . "\n" || $message === false) {
            $message = "[telegram-chats ingest]\n" . print_r($payload, true);
        }

        $chunks = $this->splitForTelegram($message, self::TELEGRAM_MESSAGE_LIMIT);
        $totalChunks = count($chunks);

        foreach ($chunks as $index => $chunk) {
            $part = $index + 1;
            $prefix = $totalChunks > 1 ? "[part {$part}/{$totalChunks}]\n" : '';
            $safeChunk = htmlspecialchars($chunk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            Yii::$app->telegramChats->sendMessage($prefix . $safeChunk);
        }

        return [
            'success' => true,
            'sent' => true,
            'parts' => $totalChunks,
        ];
    }

    private function splitForTelegram(string $text, int $limit): array
    {
        if ($text === '') {
            return [''];
        }

        $length = mb_strlen($text, 'UTF-8');
        if ($length <= $limit) {
            return [$text];
        }

        $chunks = [];
        $offset = 0;

        while ($offset < $length) {
            $size = min($limit, $length - $offset);
            $slice = mb_substr($text, $offset, $size, 'UTF-8');

            if ($offset + $size < $length) {
                $breakPos = max(
                    mb_strrpos($slice, "\n", 0, 'UTF-8'),
                    mb_strrpos($slice, ' ', 0, 'UTF-8')
                );

                if ($breakPos !== false && $breakPos > (int)($limit * 0.6)) {
                    $slice = mb_substr($slice, 0, $breakPos + 1, 'UTF-8');
                    $size = mb_strlen($slice, 'UTF-8');
                }
            }

            $chunks[] = $slice;
            $offset += $size;
        }

        return $chunks;
    }
}
