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
        $data = json_decode(Yii::$app->request->rawBody, 1);
        $response = (clone \Yii::$app->curl)
            ->setHeader('X-API-Key', '03f6a4103d7d4820bed03f4322f72f26')
            ->setHeader('x-org-id', '80768c5712f64555bab1e2cae7441429')
            ->setHeader('Content-Type', 'application/json')
            ->get('https://api.rustmaps.com/v4/maps/' . $data['Id']);

        Yii::$app->telegramChats->sendMessage($response);
        $response = json_decode($response, 1);

        return [
            'success' => true
        ];
    }
}