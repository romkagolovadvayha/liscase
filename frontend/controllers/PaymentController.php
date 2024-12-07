<?php

namespace frontend\controllers;

use common\controllers\WebController;
use common\models\invoice\Deposit;
use yii\web\NotFoundHttpException;
use common\components\web\AuthorizedControllerTrait;
use Yii;
use yii\web\Response;

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
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        if ($payment == 'anypay') {
            Yii::$app->telegramChats->sendMessage(Yii::$app->request->getRawBody());
        }
        $response = Deposit::responseAdapter(Yii::$app->request->getRawBody(), $payment);
        /** @var Deposit[] $deposits */
        $deposit = Deposit::find()
                           ->andWhere(['status' => Deposit::STATUS_WAIT_CONFIRM])
                           ->andWhere(['payment_id' => $response['id']])
                           ->one();

        if (!empty($deposit)) {
            $deposit->check();
        }

        return [
            'code' => 200,
        ];
    }
}
