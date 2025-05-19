<?php

namespace frontend\controllers;

use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\JsonResponseFormatter;
use yii\web\NotFoundHttpException;
use Yii;
use yii\web\Response;

class MirrorController extends Controller
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
        Yii::$app->telegramChats->sendMessage(Yii::$app->request->queryString);
        Yii::$app->telegramChats->sendMessage(Yii::$app->request->rawBody);
    }
}