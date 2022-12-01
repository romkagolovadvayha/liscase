<?php

namespace frontend\controllers;

use common\controllers\WebController;
use yii\web\NotFoundHttpException;
use Yii;

class PayoutController extends WebController
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

    /**
     *
     * @return \yii\web\Response | string
     * @throws NotFoundHttpException
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

}
