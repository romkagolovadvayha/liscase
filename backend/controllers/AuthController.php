<?php

namespace backend\controllers;

use backend\components\BackendController;
use Yii;
use yii\filters\AccessControl;

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
                    return $action->controller->redirect(['/']);
                },
            ],
        ];
    }

    public function actionLogin()
    {
        return $this->actionIndex();
    }

    public function actionIndex()
    {
        $this->layout = '@backend/views/layouts/blank';
        $siteUrl = rtrim(Yii::$app->params['baseUrl'] ?? '', '/') ?: '/';
        $steamLoginUrl = $siteUrl . '/auth/oauth';
        return $this->render('index', [
            'steamLoginUrl' => $steamLoginUrl,
            'siteUrl'       => $siteUrl,
        ]);
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }
}
