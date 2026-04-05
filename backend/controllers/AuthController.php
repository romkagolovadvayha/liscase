<?php

namespace backend\controllers;

use Yii;
use yii\filters\AccessControl;

/**
 * Вход в админку через Steam без зависимости от frontend: OpenID callback на backend (…/auth/oauth).
 */
class AuthController extends \common\controllers\AuthController
{
    public function init()
    {
        Yii::$app->params['homePage'] = rtrim(Yii::$app->params['backendUrl'] ?? '', '/');
        parent::init();
    }

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
                        'actions' => ['login', 'index', 'oauth'],
                        'roles'   => ['?'],
                    ],
                    [
                        'actions' => ['logout'],
                        'allow'   => true,
                        'roles'   => ['@'],
                    ],
                ],
                'denyCallback' => function ($rule, $action) {
                    return $action->controller->redirect(['/user/index']);
                },
            ],
        ];
    }

    public function getViewPath()
    {
        return '@backend/views/auth';
    }

    public function actionLogin()
    {
        return $this->actionIndex();
    }

    public function actionIndex()
    {
        $this->layout = '@backend/views/layouts/blank';
        $backendBase = rtrim(Yii::$app->params['backendUrl'] ?? '', '/');
        $steamLoginUrl = $backendBase . '/auth/oauth';
        $siteUrl = rtrim(Yii::$app->params['baseUrl'] ?? '', '/') ?: '/';

        return $this->render('index', [
            'steamLoginUrl' => $steamLoginUrl,
            'siteUrl'       => $siteUrl,
        ]);
    }
}
