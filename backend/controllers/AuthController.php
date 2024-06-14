<?php

namespace backend\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use backend\forms\LoginForm;
use yii\web\ForbiddenHttpException;

class AuthController extends Controller
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
                        'actions' => ['login'],
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

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }
}
