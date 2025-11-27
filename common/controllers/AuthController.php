<?php

namespace common\controllers;

use common\components\oauth\AuthAction;
use common\components\oauth\Steam;
use common\components\queue\process\UserSteamInfoUpdateJob;
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
                        'actions' => ['login-success', 'logout', 'oauth', 'two-step-scan', 'disable-two-step-auth', 'discord'],
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

    /**
     * Discord OAuth авторизация
     */
    public function actionDiscord()
    {
        // Детальное логирование для отладки
        Yii::$app->telegramChats->sendMessage("Discord OAuth actionDiscord called. isGuest=" . (Yii::$app->user->isGuest ? 'true' : 'false'));
        
        if (Yii::$app->user->isGuest) {
            Yii::$app->telegramChats->sendMessage("Discord OAuth: User is guest, redirecting to home");
            Yii::$app->session->setFlash('error', Yii::t('common', 'Для привязки Discord необходимо быть авторизованным.'));
            return $this->redirect(['/']);
        }

        $clientId = Yii::$app->settings->get('discord_client_id');
        $redirectUri = Yii::$app->settings->get('site_api_url') . '/discord/callback';
        $userId = Yii::$app->user->id;

        Yii::$app->telegramChats->sendMessage("Discord OAuth: clientId=" . ($clientId ? 'set (' . substr($clientId, 0, 10) . '...)' : 'empty') . ", redirectUri={$redirectUri}, userId={$userId}");

        if (empty($clientId)) {
            Yii::$app->telegramChats->sendMessage("Discord OAuth: client_id not configured. Redirecting to profile.");
            Yii::$app->session->setFlash('error', Yii::t('common', 'Discord OAuth не настроен. Обратитесь к администратору.'));
            return $this->redirect(['/user/profile']);
        }

        // Сохраняем user_id в сессии для последующей привязки
        Yii::$app->session->set('discord_oauth_user_id', $userId);

        // Сохраняем состояние для защиты от CSRF
        $state = Yii::$app->security->generateRandomString(32);
        Yii::$app->session->set('discord_oauth_state', $state);

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'identify',
            'state' => $state,
        ];

        $authUrl = 'https://discord.com/api/oauth2/authorize?' . http_build_query($params);

        Yii::$app->telegramChats->sendMessage("Discord OAuth: redirecting to Discord. authUrl length=" . strlen($authUrl));

        return $this->redirect($authUrl);
    }

    /**
     * Discord OAuth callback
     */
    public function actionDiscordCallback()
    {
        if (Yii::$app->user->isGuest) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Для привязки Discord необходимо быть авторизованным.'));
            return $this->goHome();
        }

        $code = Yii::$app->request->get('code');
        $state = Yii::$app->request->get('state');
        $error = Yii::$app->request->get('error');

        if (!empty($error)) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка при авторизации Discord: {error}', ['error' => $error]));
            return $this->redirect(['/user/profile']);
        }

        // Проверяем state для защиты от CSRF
        $savedState = Yii::$app->session->get('discord_oauth_state');
        if (empty($state) || $state !== $savedState) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка безопасности при авторизации Discord.'));
            return $this->redirect(['/user/profile']);
        }
        Yii::$app->session->remove('discord_oauth_state');

        if (empty($code)) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Код авторизации Discord не получен.'));
            return $this->redirect(['/user/profile']);
        }

        $clientId = Yii::$app->settings->get('discord_client_id');
        $clientSecret = Yii::$app->settings->get('discord_client_secret');
        $redirectUri = Yii::$app->params['homePage'] . '/auth/discord-callback';

        if (empty($clientId) || empty($clientSecret)) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Discord OAuth не настроен. Обратитесь к администратору.'));
            return $this->redirect(['/user/profile']);
        }

        // Обмениваем код на токен
        $tokenUrl = 'https://discord.com/api/oauth2/token';
        $tokenParams = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $tokenResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Yii::error("Discord OAuth token error: HTTP {$httpCode}, Response: {$tokenResponse}", __METHOD__);
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка при получении токена Discord.'));
            return $this->redirect(['/user/profile']);
        }

        $tokenData = json_decode($tokenResponse, true);
        if (empty($tokenData['access_token'])) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Токен Discord не получен.'));
            return $this->redirect(['/user/profile']);
        }

        // Получаем информацию о пользователе Discord
        $userUrl = 'https://discord.com/api/v10/users/@me';
        $ch = curl_init($userUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $tokenData['access_token'],
        ]);

        $userResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Yii::error("Discord API user error: HTTP {$httpCode}, Response: {$userResponse}", __METHOD__);
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка при получении данных пользователя Discord.'));
            return $this->redirect(['/user/profile']);
        }

        $discordUser = json_decode($userResponse, true);
        if (empty($discordUser['id'])) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'ID пользователя Discord не получен.'));
            return $this->redirect(['/user/profile']);
        }

        // Сохраняем discord_id
        $user = Yii::$app->user->identity;
        $user->discord_id = $discordUser['id'];
        if ($user->save(false)) {
            Yii::$app->session->setFlash('success', Yii::t('common', 'Discord аккаунт успешно привязан!'));
        } else {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка при сохранении Discord ID.'));
        }

        return $this->redirect(['/user/profile']);
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
                Yii::$app->queueProcess->push(new UserSteamInfoUpdateJob(['steamId' => $steamId]));


                $refCode = Cookie::getValue('refCode');
                if (!empty($refCode)) {
                    $parentUser = User::findByRefCode($refCode);
                    if (!empty($parentUser) && !empty($parentUser->telegram_chat_id) && !empty($user->getParentUser()) && !$user->getParentUser()->id != $parentUser->id) {
                        $dateTime = new \DateTime();
                        $currentDate = $dateTime->format('d.m.Y H:i:s');
                        $dateTime = new \DateTime($user->created_at);
                        $regDate = $dateTime->format('d.m.Y H:i:s');
                        Cookie::remove('refCode');
                        Yii::$app->personalBotTelegram->sendMessage($parentUser->telegram_chat_id, "По вашей ссылке пытился авторизоваться пользователь, но он уже был зарегистрирован на сайте.\nПользователь: {$user->steam_id}\nДата регистрации: {$regDate}\nТекущая дата: {$currentDate}");
                    }
                }
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

//                $infoUser = Steam::getInfoUser($steamId);
                $username = $steamId;
                $avatarLink = 'https://' . Yii::$app->settings->get('site_domain') . Yii::$app->settings->get('design_avatar_default');
//                if (!empty($infoUser)) {
//                    $username = HtmlPurifier::process($infoUser[0]['personaname']);
//                    if (empty($username)) {
//                        $username = $steamId;
//                    }
//                    $avatarLink = $infoUser[0]['avatarfull'];
//                }
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
                    $user->user_id = $user->id;
                    $user->update(false, ['user_id']);
                    if (!empty($refCode)) {
                        $parentUser = User::findByRefCode($refCode);
                        if (!empty($parentUser)) {
                            if (!empty($parentUser->telegram_chat_id)) {
                                Yii::$app->personalBotTelegram->sendMessage($parentUser->telegram_chat_id, "По вашей ссылке зарегистировался новый пользователь.\nПользователь: {$user->steam_id}");
                            }
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
                        Yii::$app->telegramChats->sendMessage('Новая регистрация на сайте: ' . $user->username);
                        Yii::$app->queueProcess->push(new UserSteamInfoUpdateJob(['steamId' => $user->steam_id]));
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
                Yii::$app->queueProcess->push(new UserSteamInfoUpdateJob(['steamId' => $steamId]));
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
