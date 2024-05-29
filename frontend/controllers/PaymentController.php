<?php

namespace frontend\controllers;

use common\controllers\WebController;
use yii\web\NotFoundHttpException;
use common\components\web\AuthorizedControllerTrait;
use Yii;
use yii\web\Response;

class PaymentController extends WebController
{
    use AuthorizedControllerTrait;

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionResult()
    {
        return $this->render('result');
    }
}
