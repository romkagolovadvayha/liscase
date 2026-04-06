<?php

namespace api\controllers;

use Yii;
use yii\web\Controller;
use yii\web\HttpException;
use yii\web\Response;

class SiteController extends Controller
{

    public function actionIndex()
    {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = Response::FORMAT_JSON;

        return [
            'success' => true
        ];
    }

    public function actionError()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $exception = Yii::$app->errorHandler->exception;

        if ($exception === null) {
            Yii::$app->response->statusCode = 500;
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Unknown error',
                    'details' => [],
                ],
            ];
        }

        $statusCode = $exception instanceof HttpException
            ? $exception->statusCode
            : 500;

        Yii::$app->response->statusCode = $statusCode;

        $errorCodeByStatus = [
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            422 => 'VALIDATION_ERROR',
            429 => 'TOO_MANY_REQUESTS',
        ];
        $errorCode = $errorCodeByStatus[$statusCode] ?? ($exception instanceof HttpException ? 'HTTP_ERROR' : 'INTERNAL_ERROR');

        if ($statusCode >= 500) {
            $message = YII_DEBUG ? $exception->getMessage() : 'Internal server error';
        } else {
            $message = $exception->getMessage();
        }

        return [
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $message,
                'details' => [],
            ],
        ];
    }
}
