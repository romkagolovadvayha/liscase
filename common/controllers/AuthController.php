<?php

namespace common\controllers;

use common\components\oauth\AuthAction;
use common\components\oauth\Steam;
use common\components\queue\process\DiscordRolesUserJob;
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
                        'actions' => ['login-success', 'logout', 'oauth', 'two-step-scan', 'disable-two-step-auth', 'discord', 'discord-callback', 'twitch', 'twitch-callback', 'kick', 'kick-callback'],
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
        if (Yii::$app->user->isGuest) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Для привязки Discord необходимо быть авторизованным.'));
            return $this->redirect(['/']);
        }

        $clientId = Yii::$app->settings->get('discord_client_id');
        // Используем frontend для redirectUri
        $baseUrl = Yii::$app->params['homePage'] ?? 'https://prostoj.store';
        $redirectUri = $baseUrl . '/auth/discord-callback';
        $userId = Yii::$app->user->id;

        if (empty($clientId)) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Discord OAuth не настроен. Обратитесь к администратору.'));
            return $this->redirect(['/user/profile']);
        }

        // Сохраняем user_id в сессии для последующей привязки
        Yii::$app->session->set('discord_oauth_user_id', $userId);

        // Сохраняем состояние для защиты от CSRF в cookie
        // Используем cookie вместо сессии, чтобы state сохранялся при редиректе от Discord
        $state = Yii::$app->security->generateRandomString(32);
        Cookie::add('discord_oauth_state', $state, true, 10); // 10 минут, с mainDomain для работы на всех поддоменах

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'identify',
            'state' => $state,
        ];

        $authUrl = 'https://discord.com/api/oauth2/authorize?' . http_build_query($params);

        return $this->redirect($authUrl);
    }

    /**
     * Discord OAuth callback
     */
    public function actionDiscordCallback()
    {
        if (Yii::$app->user->isGuest) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Для привязки Discord необходимо быть авторизованным.'));
            return $this->redirect(['/']);
        }

        $code = Yii::$app->request->get('code');
        $state = Yii::$app->request->get('state');
        $error = Yii::$app->request->get('error');

        if (!empty($error)) {
            // access_denied - это нормальная ситуация, когда пользователь отменяет авторизацию
            if ($error === 'access_denied') {
                Yii::info("Discord OAuth: User cancelled authorization", __METHOD__);
                Yii::$app->session->setFlash('info', Yii::t('common', 'Авторизация Discord отменена. Вы можете привязать Discord аккаунт позже.'));
            } else {
                Yii::warning("Discord OAuth error: {$error}", __METHOD__);
                $errorDescription = Yii::$app->request->get('error_description', $error);
                Yii::$app->session->setFlash('warning', Yii::t('common', 'Ошибка при авторизации Discord: {error}', ['error' => $errorDescription]));
            }
            return $this->redirect(['/user/profile']);
        }

        // Проверяем state для защиты от CSRF (читаем из cookie)
        $savedState = Cookie::getValue('discord_oauth_state');
        if (empty($state) || $state !== $savedState) {
            Yii::error("Discord OAuth state mismatch. State: {$state}, Saved: " . ($savedState ?? 'empty'), __METHOD__);
            Cookie::remove('discord_oauth_state');
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка безопасности при авторизации Discord.'));
            return $this->redirect(['/user/profile']);
        }
        Cookie::remove('discord_oauth_state');

        if (empty($code)) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Код авторизации Discord не получен.'));
            return $this->redirect(['/user/profile']);
        }

        $clientId = Yii::$app->settings->get('discord_client_id');
        $clientSecret = Yii::$app->settings->get('discord_client_secret');
        $baseUrl = Yii::$app->params['homePage'] ?? 'https://prostoj.store';
        $redirectUri = $baseUrl . '/auth/discord-callback';

        if (empty($clientId) || empty($clientSecret)) {
            Yii::error("Discord OAuth not configured", __METHOD__);
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
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            Yii::error("Discord OAuth token error: HTTP {$httpCode}, Response: {$tokenResponse}, cURL Error: {$curlError}", __METHOD__);
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка при получении токена Discord.'));
            return $this->redirect(['/user/profile']);
        }

        $tokenData = json_decode($tokenResponse, true);
        if (empty($tokenData['access_token'])) {
            Yii::error("Discord OAuth: no access_token in response", __METHOD__);
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
            Yii::error("Discord OAuth: no user id in response", __METHOD__);
            Yii::$app->session->setFlash('error', Yii::t('common', 'ID пользователя Discord не получен.'));
            return $this->redirect(['/user/profile']);
        }

        // Сохраняем discord_id
        $userId = Yii::$app->session->get('discord_oauth_user_id');
        if (empty($userId)) {
            $userId = Yii::$app->user->id;
        }
        Yii::$app->session->remove('discord_oauth_user_id');

        $user = User::findOne($userId);
        if ($user) {
            $user->discord_id = $discordUser['id'];
            if ($user->save(false)) {
                // Выдаем роль в Discord
                $this->assignDiscordRole($discordUser['id']);
                
                // Добавляем задачу в очередь для проверки ролей
                $checkRolesJob = new DiscordRolesUserJob();
                $checkRolesJob->userId = $user->id;
                Yii::$app->queueProcess->push($checkRolesJob);
                
                Yii::$app->session->setFlash('success', Yii::t('common', 'Discord аккаунт успешно привязан!'));
            } else {
                Yii::error("Discord OAuth: Failed to save discord_id. Errors: " . json_encode($user->getErrors()), __METHOD__);
                Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка при сохранении Discord ID.'));
            }
        } else {
            Yii::error("Discord OAuth: User not found. userId={$userId}", __METHOD__);
            Yii::$app->session->setFlash('error', Yii::t('common', 'Пользователь не найден.'));
        }

        return $this->redirect(['/user/profile']);
    }

    /**
     * Twitch OAuth — старт привязки
     */
    public function actionTwitch()
    {
        if (Yii::$app->user->isGuest) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Для привязки Twitch необходимо быть авторизованным.'));
            return $this->redirect(['/']);
        }

        $clientId = Yii::$app->settings->get('twitch_client_id');
        $baseUrl = Yii::$app->params['homePage'] ?? 'https://prostoj.store';
        $redirectUri = $baseUrl . '/auth/twitch-callback';
        $userId = Yii::$app->user->id;

        if (empty($clientId)) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Twitch OAuth не настроен. Обратитесь к администратору.'));
            return $this->redirect(['/user/profile']);
        }

        Yii::$app->session->set('twitch_oauth_user_id', $userId);
        $state = Yii::$app->security->generateRandomString(32);
        Cookie::add('twitch_oauth_state', $state, true, 10);

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'user:read:email',
            'state' => $state,
        ];
        $authUrl = 'https://id.twitch.tv/oauth2/authorize?' . http_build_query($params);
        return $this->redirect($authUrl);
    }

    /**
     * Twitch OAuth callback
     */
    public function actionTwitchCallback()
    {
        if (Yii::$app->user->isGuest) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Для привязки Twitch необходимо быть авторизованным.'));
            return $this->redirect(['/']);
        }

        $code = Yii::$app->request->get('code');
        $state = Yii::$app->request->get('state');
        $error = Yii::$app->request->get('error');

        if (!empty($error)) {
            if ($error === 'access_denied') {
                Yii::info('Twitch OAuth: User cancelled authorization', __METHOD__);
                Yii::$app->session->setFlash('info', Yii::t('common', 'Авторизация Twitch отменена.'));
            } else {
                Yii::$app->session->setFlash('warning', Yii::t('common', 'Ошибка при авторизации Twitch: {error}', ['error' => $error]));
            }
            return $this->redirect(['/user/profile']);
        }

        $savedState = Cookie::getValue('twitch_oauth_state');
        if (empty($state) || $state !== $savedState) {
            Cookie::remove('twitch_oauth_state');
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка безопасности при авторизации Twitch.'));
            return $this->redirect(['/user/profile']);
        }
        Cookie::remove('twitch_oauth_state');

        if (empty($code)) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Код авторизации Twitch не получен.'));
            return $this->redirect(['/user/profile']);
        }

        $clientId = Yii::$app->settings->get('twitch_client_id');
        $clientSecret = Yii::$app->settings->get('twitch_client_secret');
        $baseUrl = Yii::$app->params['homePage'] ?? 'https://prostoj.store';
        $redirectUri = $baseUrl . '/auth/twitch-callback';

        if (empty($clientId) || empty($clientSecret)) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Twitch OAuth не настроен.'));
            return $this->redirect(['/user/profile']);
        }

        $tokenUrl = 'https://id.twitch.tv/oauth2/token';
        $tokenParams = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ];
        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $tokenResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Yii::error("Twitch OAuth token error: HTTP {$httpCode}, Response: {$tokenResponse}", __METHOD__);
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка при получении токена Twitch.'));
            return $this->redirect(['/user/profile']);
        }
        $tokenData = json_decode($tokenResponse, true);
        if (empty($tokenData['access_token'])) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Токен Twitch не получен.'));
            return $this->redirect(['/user/profile']);
        }

        $userUrl = 'https://api.twitch.tv/helix/users';
        $ch = curl_init($userUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $tokenData['access_token'],
            'Client-Id: ' . $clientId,
        ]);
        $userResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            Yii::error("Twitch API user error: HTTP {$httpCode}, Response: {$userResponse}", __METHOD__);
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка при получении данных пользователя Twitch.'));
            return $this->redirect(['/user/profile']);
        }
        $data = json_decode($userResponse, true);
        $twitchUser = $data['data'][0] ?? null;
        $twitchId = $twitchUser['id'] ?? null;
        $twitchLogin = $twitchUser['login'] ?? null;
        if (empty($twitchId)) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'ID пользователя Twitch не получен.'));
            return $this->redirect(['/user/profile']);
        }

        $userId = Yii::$app->session->get('twitch_oauth_user_id') ?: Yii::$app->user->id;
        Yii::$app->session->remove('twitch_oauth_user_id');
        $user = User::findOne($userId);
        if ($user) {
            $user->twitch_id = (string)$twitchId;
            if ($user->save(false)) {
                if (!empty($user->userProfile) && !empty($twitchLogin)) {
                    $user->userProfile->twitch_link = 'https://www.twitch.tv/' . $twitchLogin;
                    $user->userProfile->save(false);
                }
                Yii::$app->session->setFlash('success', Yii::t('common', 'Twitch аккаунт успешно привязан!'));
            } else {
                Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка при сохранении Twitch ID.'));
            }
        } else {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Пользователь не найден.'));
        }
        return $this->redirect(['/user/profile']);
    }

    /**
     * Kick OAuth 2.1 (PKCE) — старт привязки.
     * Документация: https://docs.kick.com/getting-started/generating-tokens-oauth2-flow
     */
    public function actionKick()
    {
        if (Yii::$app->user->isGuest) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Для привязки Kick необходимо быть авторизованным.'));
            return $this->redirect(['/']);
        }

        $clientId = Yii::$app->settings->get('kick_client_id');
        $baseUrl = Yii::$app->params['homePage'] ?? 'https://prostoj.store';
        $redirectUri = $baseUrl . '/auth/kick-callback';
        $userId = Yii::$app->user->id;

        if (empty($clientId)) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Kick OAuth не настроен. Обратитесь к администратору.'));
            return $this->redirect(['/user/profile']);
        }

        $state = Yii::$app->security->generateRandomString(32);
        $codeVerifier = Yii::$app->security->generateRandomString(64);
        $codeChallenge = strtr(rtrim(base64_encode(hash('sha256', $codeVerifier, true)), '='), '+/', '-_');

        Yii::$app->session->set('kick_oauth_user_id', $userId);
        Yii::$app->session->set('kick_oauth_state', $state);
        Yii::$app->session->set('kick_oauth_code_verifier', $codeVerifier);

        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'user:read',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];
        $authUrl = 'https://id.kick.com/oauth/authorize?' . http_build_query($params);
        return $this->redirect($authUrl);
    }

    /**
     * Kick OAuth callback
     */
    public function actionKickCallback()
    {
        if (Yii::$app->user->isGuest) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Для привязки Kick необходимо быть авторизованным.'));
            return $this->redirect(['/']);
        }

        $code = Yii::$app->request->get('code');
        $state = Yii::$app->request->get('state');
        $error = Yii::$app->request->get('error');

        if (!empty($error)) {
            if ($error === 'access_denied') {
                Yii::info('Kick OAuth: User cancelled authorization', __METHOD__);
                Yii::$app->session->setFlash('info', Yii::t('common', 'Авторизация Kick отменена.'));
            } else {
                Yii::$app->session->setFlash('warning', Yii::t('common', 'Ошибка при авторизации Kick: {error}', ['error' => $error]));
            }
            return $this->redirect(['/user/profile']);
        }

        $savedState = Yii::$app->session->get('kick_oauth_state');
        $codeVerifier = Yii::$app->session->get('kick_oauth_code_verifier');
        if (empty($state) || $state !== $savedState || empty($codeVerifier)) {
            Yii::$app->session->remove('kick_oauth_state');
            Yii::$app->session->remove('kick_oauth_code_verifier');
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка безопасности при авторизации Kick.'));
            return $this->redirect(['/user/profile']);
        }
        Yii::$app->session->remove('kick_oauth_state');
        Yii::$app->session->remove('kick_oauth_code_verifier');

        if (empty($code)) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Код авторизации Kick не получен.'));
            return $this->redirect(['/user/profile']);
        }

        $clientId = Yii::$app->settings->get('kick_client_id');
        $clientSecret = Yii::$app->settings->get('kick_client_secret');
        $baseUrl = Yii::$app->params['homePage'] ?? 'https://prostoj.store';
        $redirectUri = $baseUrl . '/auth/kick-callback';

        if (empty($clientId) || empty($clientSecret)) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Kick OAuth не настроен.'));
            return $this->redirect(['/user/profile']);
        }

        $tokenUrl = 'https://id.kick.com/oauth/token';
        $tokenParams = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ];
        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $tokenResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Yii::error("Kick OAuth token error: HTTP {$httpCode}, Response: {$tokenResponse}", __METHOD__);
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка при получении токена Kick.'));
            return $this->redirect(['/user/profile']);
        }
        $tokenData = json_decode($tokenResponse, true);
        if (empty($tokenData['access_token'])) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Токен Kick не получен.'));
            return $this->redirect(['/user/profile']);
        }

        $userUrl = 'https://api.kick.com/public/v1/users';
        $ch = curl_init($userUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $tokenData['access_token'],
        ]);
        $userResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            Yii::error("Kick API user error: HTTP {$httpCode}, Response: {$userResponse}", __METHOD__);
            Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка при получении данных пользователя Kick.'));
            return $this->redirect(['/user/profile']);
        }
        $data = json_decode($userResponse, true);
        $kickUser = isset($data['data']) ? $data['data'] : $data;
        if (isset($kickUser[0]) && is_array($kickUser[0])) {
            $kickUser = $kickUser[0];
        }
        $kickId = isset($kickUser['id']) ? (string)$kickUser['id'] : (isset($kickUser['user_id']) ? (string)$kickUser['user_id'] : (isset($data['id']) ? (string)$data['id'] : null));
        $channel = $kickUser['channel'] ?? $data['channel'] ?? null;
        $kickSlug = $kickUser['slug'] ?? $kickUser['username'] ?? $kickUser['login']
            ?? (is_array($channel) ? ($channel['slug'] ?? $channel['username'] ?? $channel['user_name'] ?? null) : null)
            ?? $data['slug'] ?? $data['username'] ?? null;
        if (empty($kickId)) {
            Yii::$app->session->setFlash('error', Yii::t('common', 'ID пользователя Kick не получен.'));
            return $this->redirect(['/user/profile']);
        }

        $userId = Yii::$app->session->get('kick_oauth_user_id') ?: Yii::$app->user->id;
        Yii::$app->session->remove('kick_oauth_user_id');
        $user = User::findOne($userId);
        if ($user) {
            $user->kick_id = $kickId;
            if ($user->save(false)) {
                if (!empty($user->userProfile) && $user->userProfile->hasAttribute('kick_link')) {
                    $user->userProfile->kick_link = !empty($kickSlug)
                        ? 'https://kick.com/' . $kickSlug
                        : 'https://kick.com/channel/' . $kickId;
                    $user->userProfile->save(false);
                }
                Yii::$app->session->setFlash('success', Yii::t('common', 'Kick аккаунт успешно привязан!'));
            } else {
                Yii::$app->session->setFlash('error', Yii::t('common', 'Ошибка при сохранении Kick ID.'));
            }
        } else {
            Yii::$app->session->setFlash('error', Yii::t('common', 'Пользователь не найден.'));
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
                    // Сохраняем URL аватара из Steam вместо загрузки на сервер
                    if (!empty($avatarLink)) {
                        $user->userProfile->steam_avatar_url = $avatarLink;
                    }
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
        
        if (!$user) {
            Yii::$app->session->addFlash('error', Yii::t('common', 'Пользователь не найден'));
            return $this->redirect(Yii::$app->getHomeUrl());
        }

        if (Yii::$app->user->isGuest) {
            Yii::$app->user->login($user, 3600 * 24);
        } else {
            Yii::$app->user->logout();
            Yii::$app->user->switchIdentity($user, 3600 * 24);
        }

        $redirectUrl = Yii::$app->getHomeUrl();
        
        if (!empty($parentUser)) {
            Cookie::add('fromSwitcherUserId', $parentUser, true);
        }

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

    /**
     * Удаляет роль у пользователя в Discord сервере
     * @param string $discordUserId Discord User ID
     * @return bool
     */
    public static function removeDiscordRole($discordUserId)
    {
        $guildId = Yii::$app->settings->get('discord_guild_id');
        $botToken = Yii::$app->settings->get('discord_bot_token');
        $roleId = Yii::$app->settings->get('discord_role_confirm');

        if (empty($guildId) || empty($botToken) || empty($roleId)) {
            Yii::warning("Discord role removal: guild_id, bot_token or role_id not configured", __METHOD__);
            return false;
        }

        try {
            $url = "https://discord.com/api/v10/guilds/{$guildId}/members/{$discordUserId}/roles/{$roleId}";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bot ' . $botToken,
                'Content-Type: application/json',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 204 || $httpCode === 200) {
                return true;
            } else {
                // Ошибка при удалении роли
                Yii::error("Discord API error removing role: HTTP {$httpCode}, Response: {$response}, cURL Error: {$curlError}", __METHOD__);
                Yii::$app->telegramChats->sendMessage("Discord: Failed to remove role {$roleId} from user {$discordUserId}. HTTP {$httpCode}, Response: {$response}");
                return false;
            }
        } catch (\Exception $e) {
            Yii::error("Discord role removal exception: " . $e->getMessage(), __METHOD__);
            Yii::$app->telegramChats->sendMessage("Discord: Exception removing role: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Выдает роль пользователю в Discord сервере
     * @param string $discordUserId Discord User ID
     * @return bool
     */
    private function assignDiscordRole($discordUserId)
    {
        $guildId = Yii::$app->settings->get('discord_guild_id');
        $botToken = Yii::$app->settings->get('discord_bot_token');
        $roleId = Yii::$app->settings->get('discord_role_confirm');

        if (empty($guildId) || empty($botToken) || empty($roleId)) {
            Yii::warning("Discord role assignment: guild_id, bot_token or role_id not configured", __METHOD__);
            return false;
        }

        try {
            $url = "https://discord.com/api/v10/guilds/{$guildId}/members/{$discordUserId}/roles/{$roleId}";

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bot ' . $botToken,
                'Content-Type: application/json',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 204 || $httpCode === 200) {
                return true;
            } else {
                // Парсим ответ для проверки кода ошибки
                $errorData = json_decode($response, true);
                $errorCode = $errorData['code'] ?? null;
                
                // Ошибка 10007 = "Unknown Member" - пользователь не является участником сервера
                if ($errorCode === 10007) {
                    // Это нормальная ситуация - пользователь привязал Discord, но не на сервере
                    Yii::warning("Discord user {$discordUserId} is not a member of the server (code 10007). Role assignment skipped.", __METHOD__);
                    return false;
                }
                
                // Другие ошибки логируем как обычно
                Yii::error("Discord API error assigning role: HTTP {$httpCode}, Response: {$response}, cURL Error: {$curlError}", __METHOD__);
                Yii::$app->telegramChats->sendMessage("Discord: Failed to assign role {$roleId} to user {$discordUserId}. HTTP {$httpCode}, Response: {$response}");
                return false;
            }
        } catch (\Exception $e) {
            Yii::error("Discord role assignment exception: " . $e->getMessage(), __METHOD__);
            Yii::$app->telegramChats->sendMessage("Discord: Exception assigning role: " . $e->getMessage());
            return false;
        }
    }
}
