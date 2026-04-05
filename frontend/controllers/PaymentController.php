<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\components\payments\PaymentCallbackHandler;
use yii\web\NotFoundHttpException;
use Yii;

class PaymentController extends WebController
{

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

    public function beforeAction($action)
    {
        $this->_setRefCookies();
        return true;
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

    /**
     * @return int[]
     * @throws \Exception
     */
    public function actionCallback($payment)
    {
        return PaymentCallbackHandler::handle($payment);
    }
}
