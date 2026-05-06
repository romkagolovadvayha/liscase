<?php

namespace api\controllers;

use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

class TelegramChatsController extends Controller
{
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
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        Yii::$app->telegramChats->sendMessage($message);

        return [
            'success' => true,
            'sent' => true,
        ];
    }
}
