<?php

namespace api\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

class RustMapsController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['webhook'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'webhook' => ['POST'],
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

    public function actionWebhook()
    {
        $body = Yii::$app->request->rawBody;
        if ($body === '' || $body === null) {
            $params = Yii::$app->request->bodyParams;
            $body = $params ? json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        }

        try {
            Yii::$app->telegramChats->sendMessage($body ?: '[rustmaps webhook] empty payload');
        } catch (\Throwable $throwable) {
            Yii::error(
                'RustMaps webhook failed to forward payload: ' . $throwable->getMessage(),
                __METHOD__
            );

            return [
                'success' => false,
                'error' => 'telegram_send_failed',
                'message' => $throwable->getMessage(),
            ];
        }

        return [
            'success' => true,
        ];
    }
}

