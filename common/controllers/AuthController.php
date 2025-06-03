<?php

namespace common\controllers;

use common\components\oauth\AuthAction;
use common\components\oauth\Steam;
use common\forms\user\LoginForm;
use common\models\profit\Profit;
use common\models\user\Auth;
use common\models\user\UserProfile;
use common\models\user\UserTree;
use Vikas5914\SteamAuth;
use Yii;
use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\helpers\HtmlPurifier;
use yii\web\BadRequestHttpException;
use common\components\web\Cookie;
use common\models\user\User;
use yii\web\HttpException;

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
                            'login-social',
                            'registration',
                            'request-password-reset',
                            'confirm-email',
                            'alert-page',
                            'login',
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

//    public function actions()
//    {
//        return [
//            'oauth' => [
//                'class'           => AuthAction::class,
//                'successCallback' => [$this, 'onAuthSuccess'],
//            ],
//        ];
//    }

    public function actionOauth()
    {
        $config = [
            'apikey' => Yii::$app->settings->get('steam_apiKey'), // Steam API KEY
            'domainname' => Yii::$app->params['homePage'] . '/', // Displayed domain in the login-screen
            'loginpage' => Yii::$app->params['homePage'] . '/auth/oauth', // Returns to last page if not set
            "logoutpage" => "",
            "skipAPI" => true, // true = dont get the data from steam, just return the steamid64
        ];

        $steam = new SteamAuth($config);
        if ($steam->loggedIn()) {
            return $this->redirect($this->onAuthSuccess($_SESSION['steamdata']['steamid']));
        }
        return $this->redirect($steam->loginUrl());
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

    public function onAuthSuccess($steamId)
    {
        $source = 'steam';
        /* @var $auth Auth */
        $auth = Auth::find()->where([
                'source' => $source,
                'source_id' => $steamId,
            ])->one();

        if (Yii::$app->user->isGuest) {
            $referer = Yii::$app->session->get('referer_link');
            Yii::$app->session->remove('referer_link');
            if ($auth) {
                // авторизация
                $user = $auth->user;
//                try {
//                    $avatar = $this->_loadImage($attributes['avatar_link'], $attributes['id']);
//                    $user->userProfile->avatar = $avatar;
//                } catch (\Exception $ex) {}
//                try {
//                    $user->userProfile->name = $attributes['username'];
//                    $user->userProfile->save(false);
//                    $user->username = $attributes['username'];
//                    $user->save(false);
//                } catch (\Exception $ex) {}
                Yii::$app->user->login($user,3600*24*7);
            } else {

                $infoUser = Steam::getInfoUser($steamId);
                $username = $steamId;
                $avatarLink = Yii::$app->settings->get('design_avatar_default');
                if (!empty($infoUser)) {
                    $username = HtmlPurifier::process($infoUser[0]['personaname']);
                    if (empty($username)) {
                        $username = $steamId;
                    }
                    $avatarLink = $infoUser[0]['avatarfull'];
                }
                // регистрация
                $user     = new User();
                $user->email = "{$steamId}@steam.com";
                $user->steam_id = $steamId;
                $user->username = $username;
                $user->setPassword(Yii::$app->security->generateRandomString());
                $user->status = User::STATUS_ACTIVE;
                $user->generateAuthKey();
                $user->generateRefCode();
                $user->generateSocketRoom();
                $refCode = Cookie::getValue('refCode');
                $transaction = $user->getDb()->beginTransaction();
                if ($user->save(false)) {
                    if (!empty($refCode)) {
                        $parentUser = User::findByRefCode($refCode);
                        if (!empty($parentUser)) {
                            UserTree::appendUser($user->id, $parentUser->id);
                        } else {
                            UserTree::appendUser($user->id, 509);
                        }
                    } else {
                        UserTree::appendUser($user->id, 509);
                    }
                    UserProfile::createModel($user, $username);
                    try {
                        $avatar = $this->_loadImage($avatarLink, $steamId);
                        $user->userProfile->avatar = $avatar;
                    } catch (\Exception $ex) {}
                    $user->userProfile->save();
                    $auth = new Auth(
                        [
                            'user_id'   => $user->id,
                            'source' => $source,
                            'source_id' => (string)$steamId,
                        ]
                    );
                    if ($auth->save(false)) {
                        $transaction->commit();
//                        $userBalance = $user->getPersonalBalance();
//                        $model = new Profit();
//                        $model->user_balance_id   = $userBalance->id;
//                        $model->amount            = 50;
//                        $model->type              = Profit::TYPE_BONUS;
//                        $model->comment           = Yii::t('common', 'Стартовый баланс', [], 'ru-RU');
//                        $model->status            = 1;
//                        $model->save();
//                        $userBalance->recalculateBalance();
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
            if (!empty($referer)) {
                return $this->redirect($referer);
            }
        } else {
            // Пользователь уже зарегистрирован
            if (!$auth) { // добавляем внешний сервис аутентификации
                $auth = new Auth(
                    [
                        'user_id'   => Yii::$app->user->id,
                        'source' => $source,
                        'source_id' => $steamId,
                    ]
                );
                $auth->save();
            }
        }

        return Yii::$app->params['homePage'];
    }

    private function _loadImage($imageUrl, $id) {
        $uploadDir = Yii::getAlias('@app/web');
        $fileUrl = "/uploads/avatar/steam/{$id}.png";
        $filePath = $uploadDir . $fileUrl;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        if (!file_exists(dirname(dirname($filePath)))) {
            mkdir(dirname(dirname($filePath)));
            chmod(dirname(dirname($filePath)), 0777);
        }
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, file_get_contents($imageUrl));
        return $fileUrl;
    }

    public function actionLoginSocial()
    {
        return $this->render('_oauth-clients', [
            'title' => Yii::t('common', 'Войти через соц.сети'),
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

    public function actionAlertPage($alertText)
    {
        $this->layout = '@common/views/layouts/blank';

        return $this->render('alert-page', [
            'alertText' => $alertText,
        ]);
    }

    public function actionLogin()
    {
        $this->layout = '@frontend/views/layouts/main';

        $model = new LoginForm();

        $model->email = Cookie::getValue('currentEmail');

        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->redirect('/');
        }

        $model->password = null;

        return $this->render('login', [
            'model' => $model,
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
