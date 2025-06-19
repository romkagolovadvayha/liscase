<?php

namespace api\controllers;

use common\models\mirrors\Mirrors;
use common\models\user\User;
use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\JsonResponseFormatter;
use yii\web\NotFoundHttpException;
use Yii;
use yii\web\Response;

class MapController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * @return array
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['webhook']
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ]);
    }

    public function actionWebhook()
    {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        //$data = json_decode(Yii::$app->request->rawBody, 1);

        Yii::$app->telegramChats->sendMessage(ii::$app->request->rawBody);

        return [
            'success' => true
        ];
    }
}