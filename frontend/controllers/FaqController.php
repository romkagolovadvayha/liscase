<?php

namespace frontend\controllers;

use yii\web\Controller;
use yii\web\NotFoundHttpException;
use Yii;

class FaqController extends Controller
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
