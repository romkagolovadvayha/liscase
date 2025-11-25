<?php

namespace backend\controllers;

use backend\components\BackendController;
use Yii;
use yii\filters\AccessControl;
use backend\forms\LoginForm;
use yii\web\ForbiddenHttpException;

class AuthController extends BackendController
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class'        => AccessControl::class,
                'rules'        => [
                    [
                        'allow'   => true,
                        'actions' => ['login', 'index'],
                        'roles'   => ['?'],
                    ],
                    [
                        'actions' => ['logout'],
                        'allow'   => true,
                        'roles'   => ['@'],
                    ],
                ],
                'denyCallback' => function ($rule, $action) {
                    return $action->controller->redirect('/');
                },
            ],
        ];
    }

    public function actionLogin()
    {
        $this->layout = '@common/views/layouts/blank';
        return 'Ошибка доступа!';
    }

    public function actionIndex()
    {
        $this->layout = '@common/views/layouts/blank';
        return 'Ошибка доступа!';
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }
}
