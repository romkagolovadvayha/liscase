<?php

namespace api\controllers;

use common\controllers\WebController;
use Yii;
use yii\web\Controller;

class SiteController extends Controller
{

    public function actionIndex()
    {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        return [
            'success' => true
        ];
    }

    public function actionError()
    {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $exception = Yii::$app->errorHandler->exception;

        if ($exception !== null) {
            // Пример для API: возврат JSON
            return [
                'success' => false,
                'name'    => $exception->getName(),
                'message' => $exception->getMessage(),
                'code'    => $exception->statusCode ?? $exception->getCode(),
            ];
        }
        return [
            'success' => false
        ];
    }
}
