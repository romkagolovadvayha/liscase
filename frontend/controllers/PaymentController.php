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
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionCallback()
    {
        header('Content-type: application/json');

        /** @var Deposit[] $deposits */
        $deposits = Deposit::find()
                           ->andWhere(['status' => Deposit::STATUS_WAIT_CONFIRM])
                           ->andWhere('payment_id is not null')
                           ->all();

        foreach ($deposits as $deposit) {
            $deposit->check();
        }

        return json_encode([
                               'code' => 200,
                           ],JSON_PRETTY_PRINT);
    }
}
