<?php

namespace backend\controllers;

use backend\components\BackendController;
use common\components\helpers\Role;
use Yii;
use yii\filters\AccessControl;
use yii\web\Response;
use yii\filters\VerbFilter;

class SiteController extends BackendController
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => \yii\filters\AccessControl::class,
                'rules' => [
                    // Иначе при 403 ErrorHandler не может отрендерить site/error — снова 403 («ошибка при обработке ошибки»).
                    [
                        'actions' => ['error', 'captcha'],
                        'allow' => true,
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string|Response
     */
    public function actionIndex()
    {
        // Редирект для ROLE_SUPPORT на блог
        if (Yii::$app->user->can(Role::ROLE_SUPPORT) && !Yii::$app->user->can(Role::ROLE_ADMIN) && !Yii::$app->user->can(Role::ROLE_MODERATOR)) {
            return $this->redirect(['/blog']);
        }
        return $this->render('index');
    }

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            // Редирект для ROLE_SUPPORT на блог
            if (Yii::$app->user->can(Role::ROLE_SUPPORT) && !Yii::$app->user->can(Role::ROLE_ADMIN) && !Yii::$app->user->can(Role::ROLE_MODERATOR)) {
                return $this->redirect(['/blog']);
            }
            return $this->goHome();
        }

        $model = new \backend\forms\LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            // Редирект для ROLE_SUPPORT на блог после логина
            if (Yii::$app->user->can(Role::ROLE_SUPPORT) && !Yii::$app->user->can(Role::ROLE_ADMIN) && !Yii::$app->user->can(Role::ROLE_MODERATOR)) {
                return $this->redirect(['/blog']);
            }
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }
}
