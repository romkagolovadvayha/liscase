<?php

namespace common\controllers;

use common\models\user\Auth;
use common\models\user\UserProfile;
use Yii;
use yii\filters\AccessControl;
use yii\web\BadRequestHttpException;
use common\components\web\Cookie;
use common\models\user\User;

class AuthController extends WebController
{
    public $layout   = '@common/views/layouts/login';
    public $boxClass = 'login-box';

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
                        'actions' => ['switch-identity'],
                        'allow'   => true,
                    ],
                    [
                        'allow'   => true,
                        'actions' => [
                            'login',
                            'login-social',
                            'registration',
                            'request-password-reset',
                            'reset-password',
                            'confirm-email',
                            'alert-page',
                            'oauth',
                        ],
                        'roles'   => ['?'],
                    ],
                    [
                        'actions' => ['login-success', 'logout', 'oauth', 'two-step-scan', 'disable-two-step-auth'],
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

    public function actions()
    {
        return [
            'oauth' => [
                'class'           => 'yii\authclient\AuthAction',
                'successCallback' => [$this, 'onAuthSuccess'],
            ],
        ];
    }

    public function init()
    {
        parent::init();

        Yii::$app->session->getFlash('frontendProjectUrl');
    }

    public function getViewPath()
    {
        return '@common/views/auth';
    }

    public function onAuthSuccess($client)
    {
        $attributes = $client->getUserAttributes();
        /* @var $auth Auth */
        $auth = Auth::find()->where([
                'source' => $client->getId(),
                'source_id' => $attributes['id'],
            ])->one();

        if (Yii::$app->user->isGuest) {
            if ($auth) {
                // авторизация
                $user = $auth->user;
                Yii::$app->user->login($user,3600*24*7);
            } else {
                // регистрация
                if (isset($attributes['email']) && User::find()->where(['email' => $attributes['email']])->exists()) {
                    Yii::$app->getSession()->setFlash(
                        'error', [
                        Yii::t(
                            'app',
                            "Пользователь с такой электронной почтой как в {client} уже существует, но с ним не связан. Для начала войдите на сайт использую электронную почту, для того, что бы связать её.",
                            ['client' => $client->getTitle()]
                        ),
                    ]
                    );
                } else {
                    $user     = new User();
                    $user->email = "{$attributes['id']}@steam.com";
                    $user->setPassword(Yii::$app->security->generateRandomString());
                    $user->status = User::STATUS_ACTIVE;
                    $user->generateAuthKey();
                    $user->generateRefCode();
                    $user->generateSocketRoom();
                    $transaction = $user->getDb()->beginTransaction();
                    if ($user->save()) {
                        UserProfile::createModel($user, $attributes['username']);
                        $user->userProfile->avatar = $attributes['avatar'];
                        $user->userProfile->save();
                        $auth = new Auth(
                            [
                                'user_id'   => $user->id,
                                'source' => $client->getId(),
                                'source_id' => (string)$attributes['id'],
                            ]
                        );
                        if ($auth->save()) {
                            $transaction->commit();
                            Yii::$app->user->login($user,3600*24*7);
                        }
                        else {
                            print_r($auth->getErrors());
                        }
                    }
                    else {
                        print_r($user->getErrors());
                    }
                }
            }
        } else { // Пользователь уже зарегистрирован
            if (!$auth) { // добавляем внешний сервис аутентификации
                $auth = new Auth(
                    [
                        'user_id'   => Yii::$app->user->id,
                        'source' => $client->getId(),
                        'source_id' => $attributes['id'],
                    ]
                );
                $auth->save();
            }
        }
    }

    public function actionLoginSocial()
    {
        return $this->render('_oauth-clients', [
            'title' => Yii::t('common', 'Войти через соц.сети'),
        ]);
    }

    public function actionLogin()
    {
        $model = new LoginForm();

        $model->email = Cookie::getValue('currentEmail');

        if ($model->load(Yii::$app->request->post()) && $model->login()) {

            Cookie::remove('closedConfirmGiftDigiuBonusModal');
            if (Yii::$app->user->identity->two_step_auth) {
                return $this->redirect('two-step-scan');
            }

            return $this->_loginSuccess();
        }

        $model->password = null;

        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * @return \yii\web\Response
     */
    private function _loginSuccess()
    {
        return $this->redirect(['login-success', 'url' => $this->_getRedirectUrl()]);
    }

    private function _getRedirectUrl()
    {
        $redirectUrl = Yii::$app->session->getFlash('frontendProjectUrl');
        if (empty($redirectUrl)) {
            $redirectUrl = Yii::$app->session->getFlash('redirectUrl');
            if (empty($redirectUrl)) {
                $redirectUrl = Yii::$app->user->returnUrl;
                if (strstr($redirectUrl, '/auth/')) {
                    $redirectUrl = '/';
                }
            }
        }

        return $redirectUrl;
    }


    /**
     * @param $userId
     * @param array $rolesArr
     * @return void
     * @throws \Exception
     */
    public function updateUserRoles($userId, array $rolesArr): void
    {
        if($userId && !empty($rolesArr)){
            Yii::$app->authManager->revokeAll($userId);
            $ifRoleUserNotExists = true;
            foreach ($rolesArr as $role){
                // если забыли роль USER выставить
                if($role === 'USER'){
                    $ifRoleUserNotExists = false;
                }
                $role = Yii::$app->authManager->getRole($role);
                Yii::$app->authManager->assign($role, $userId);
            }
            if($ifRoleUserNotExists){
                $role = Yii::$app->authManager->getRole('USER');
                Yii::$app->authManager->assign($role, $userId);
            }
        }
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequestForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail()) {
                $alertText = Yii::t('common', 'Ссылка на восстановление пароля отправлена на указанный E-mail');

                return $this->redirect(['alert-page', 'alertText' => $alertText]);

            } else {
                Yii::$app->session->setFlash('error',
                    Yii::t('common', 'К сожалению, мы не можем отправить письмо на указанный E-mail'));
            }
        }

        return $this->render('request-password-reset', [
            'model' => $model,
        ]);
    }



    public function actionAlertPage($alertText)
    {
        $this->layout = '@common/views/layouts/blank';

        return $this->render('alert-page', [
            'alertText' => $alertText,
        ]);
    }

    public function actionLoginSuccess($url = null)
    {
        $this->layout = '@common/views/layouts/blank';

        if (empty($url)) {
            $url = '/';
        }

        return $this->render('login-success', [
            'url' => $url,
        ]);
    }

    public function actionSwitchIdentity($authKey, $parentUser = null)
    {
        $user = (new User())->findByAuthKey($authKey);

        if (Yii::$app->user->isGuest) {
            Yii::$app->user->login($user, 3600 * 24);

        } else {
            Yii::$app->user->logout();
            Yii::$app->user->switchIdentity($user);
        }

        if (empty($redirectUrl)) {
            $redirectUrl = Yii::$app->getHomeUrl();
        }

        Cookie::add('fromSwitcherUserId', $parentUser, true);

        return $this->redirect($redirectUrl);
    }


    public function actionDisableTwoStepAuth()
    {
        $user = Yii::$app->user->identity;

        $user->two_step_auth     = 0;
        $user->two_step_auth_key = null;
        $user->save(false);

        return $this->redirect('/cabinet/profile/two-step-auth');
    }
}
