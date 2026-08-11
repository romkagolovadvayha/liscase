<?php

namespace api\controllers\v1;

use Yii;
use yii\web\Response;
use yii\web\Cookie;
use yii\authclient\OpenId;
use common\models\user\User;
use common\models\user\UserTree;
use common\components\web\Cookie as WebCookie;
use common\components\helpers\Role;
use common\components\oauth\Steam;
use api\components\jwt\JwtService;
use api\components\jwt\JwtAuthFilter;
use Vikas5914\SteamAuth;
use OpenApi\Annotations as OA;

/**
 * Контроллер для авторизации и аутентификации
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="Auth")
 */
class AuthController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // JWT авторизация не требуется для OAuth, callback и refresh
        // Но требуется для me, logout
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'except' => ['oauth', 'callback', 'login', 'refresh', 'exchange', 'options'],
            'throwException' => false, // Не выбрасывать исключение, просто не авторизовывать
            // Привязка Twitch/Discord/Kick: переход по ссылке с фронта без заголовка Bearer
            'queryTokenParams' => ['access_token'],
        ];

        return $behaviors;
    }

    /**
     * Редирект на Steam OAuth
     * 
     * @OA\Get(
     *     path="/v1/auth/oauth",
     *     operationId="authOauth",
     *     tags={"Auth"},
     *     summary="Начать OAuth авторизацию через Steam",
     *     description="Перенаправляет на страницу авторизации Steam",
     *     @OA\Response(response=302, description="Редирект на Steam OAuth")
     * )
     */
    public function actionOauth()
    {
        // Сохраняем redirect_uri в сессии и cookie (для надежности), если он передан
        $redirectUri = $this->sanitizeFrontendUrl(Yii::$app->request->get('redirect_uri'));
        if ($redirectUri) {
            $_SESSION['oauth_redirect_uri'] = $redirectUri;
            // Также сохраняем в cookie как резервный способ (на 10 минут)
            Yii::$app->response->cookies->add(new Cookie([
                'name' => 'oauth_redirect_uri',
                'value' => $redirectUri,
                'expire' => time() + 600,
                'httpOnly' => true,
                'sameSite' => Cookie::SAME_SITE_LAX,
            ]));
        } else {
            // Если не передан, пробуем определить из Referer
            $referer = Yii::$app->request->getReferrer();
            if ($referer) {
                // Извлекаем базовый URL из Referer
                $parsed = parse_url($referer);
                if ($parsed && isset($parsed['scheme']) && isset($parsed['host'])) {
                    $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
                    $redirectUri = $this->sanitizeFrontendUrl($parsed['scheme'] . '://' . $parsed['host'] . $port);
                    if ($redirectUri !== null) {
                        $_SESSION['oauth_redirect_uri'] = $redirectUri;
                        Yii::$app->response->cookies->add(new Cookie([
                            'name' => 'oauth_redirect_uri',
                            'value' => $redirectUri,
                            'expire' => time() + 600,
                            'httpOnly' => true,
                            'sameSite' => Cookie::SAME_SITE_LAX,
                        ]));
                    }
                }
            }
        }

        // Реферальный refCode: cookie ставит Next.js (/p/{code}), не подписан Yii — дублируем в сессию OAuth (callback на API).
        $refForSession = $this->sanitizeReferralRefCode($_COOKIE['refCode'] ?? null)
            ?: $this->sanitizeReferralRefCode(Yii::$app->request->cookies->getValue('refCode'));
        if ($refForSession !== null) {
            $_SESSION['oauth_ref_code'] = $refForSession;
        }
        
        // URL, на который Steam вернёт пользователя после авторизации — обязательно текущий хост API,
        // иначе callback попадёт на прод/тест другой инстанс и сессия с redirect_uri будет потеряна
        $apiCallbackUrl = rtrim(Yii::$app->request->hostInfo, '/') . '/v1/auth/callback';
        $config = [
            'apikey' => Yii::$app->settings->get('steam_apiKey'), // Steam API KEY
            'domainname' => (Yii::$app->params['homePage'] ?? $apiCallbackUrl) . '/', // Displayed domain in the login-screen
            'loginpage' => $apiCallbackUrl, // Returns to callback page — текущий API, чтобы сессия сохранилась
            "logoutpage" => "",
            "skipAPI" => true, // true = dont get the data from steam, just return the steamid64
        ];

        $steam = new SteamAuth($config);
        if ($steam->loggedIn()) {
            // Пользователь уже авторизован, обрабатываем сразу
            $steamId = $_SESSION['steamdata']['steamid'];
            return $this->handleSteamAuth($steamId);
        }
        
        // Перенаправляем на Steam для авторизации
        $loginUrl = $steam->loginUrl();
        return Yii::$app->response->redirect($loginUrl);
    }

    /**
     * Обработка callback от Steam OAuth
     * 
     * @OA\Get(
     *     path="/v1/auth/callback",
     *     operationId="authCallback",
     *     tags={"Auth"},
     *     summary="Callback от Steam OAuth",
     *     description="Обрабатывает ответ от Steam OAuth и возвращает JWT токен",
     *     @OA\Response(
     *         response=200,
     *         description="Успешная авторизация",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(property="data", type="object",
     *                     @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGc..."),
     *                     @OA\Property(property="expires_in", type="integer", example=3600),
     *                     @OA\Property(property="user", type="object")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Ошибка авторизации")
     * )
     */
    public function actionCallback()
    {
        // loginpage должен совпадать с тем, что был в actionOauth (текущий хост API)
        $apiCallbackUrl = rtrim(Yii::$app->request->hostInfo, '/') . '/v1/auth/callback';
        $config = [
            'apikey' => Yii::$app->settings->get('steam_apiKey'),
            'domainname' => (Yii::$app->params['homePage'] ?? $apiCallbackUrl) . '/',
            'loginpage' => $apiCallbackUrl,
            "logoutpage" => "",
            "skipAPI" => true,
        ];

        $steam = new SteamAuth($config);
        
        try {
            if ($steam->loggedIn()) {
                $steamId = $_SESSION['steamdata']['steamid'];
                return $this->handleSteamAuth($steamId);
            }
            
            return $this->errorResponse('OAUTH_ERROR', 'Не удалось авторизоваться через Steam', [], 400);
        } catch (\Exception $e) {
            Yii::error('Steam OAuth callback error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'auth');
            return $this->errorResponse('OAUTH_ERROR', 'Ошибка при обработке callback от Steam: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Обработка авторизованного пользователя Steam
     * 
     * @param string $steamId Steam ID пользователя
     * @return \yii\web\Response
     */
    protected function handleSteamAuth($steamId)
    {
        try {
            if (empty($steamId)) {
                return $this->errorResponse('OAUTH_ERROR', 'Steam ID не получен', [], 400);
            }

            // Получаем дополнительную информацию из Steam API
            $steamInfo = Steam::getInfoUser($steamId);
            $steamAttributes = [];
            if (!empty($steamInfo[0])) {
                $steamAttributes = [
                    'personaname' => $steamInfo[0]['personaname'] ?? null,
                    'avatarfull' => $steamInfo[0]['avatarfull'] ?? null,
                ];
            }

            // Ищем или создаем пользователя
            $user = User::find()->where(['steam_id' => $steamId])->one();

            if (!$user) {
                // Создаем нового пользователя
                $user = $this->createUserFromSteam($steamId, $steamAttributes);
            } else {
                // Обновляем данные пользователя из Steam
                $this->updateUserFromSteam($user, $steamAttributes);
            }

            unset($_SESSION['oauth_ref_code']);

            // Генерируем JWT токены
            $jwtService = Yii::$app->get('jwt');
            $accessToken = $jwtService->generateToken($user->id, $user->steam_id, false);
            $refreshToken = $jwtService->generateToken($user->id, $user->steam_id, true);

            // Передаём в URL только одноразовый короткоживущий код. JWT не
            // попадают в browser history, access logs и Referer.
            // Используем сохраненный redirect_uri из сессии/cookie, или frontendUrl из параметров, или определяем из homePage
            $frontendUrl = null;
            
            // Сначала пробуем использовать сохраненный redirect_uri из сессии
            if (isset($_SESSION['oauth_redirect_uri']) && !empty($_SESSION['oauth_redirect_uri'])) {
                $frontendUrl = $this->sanitizeFrontendUrl($_SESSION['oauth_redirect_uri']);
                unset($_SESSION['oauth_redirect_uri']); // Удаляем из сессии после использования
            }
            
            // Если не нашли в сессии, пробуем из cookie (резервный способ)
            if (!$frontendUrl) {
                $cookie = Yii::$app->request->cookies->get('oauth_redirect_uri');
                if ($cookie && !empty($cookie->value)) {
                    $frontendUrl = $this->sanitizeFrontendUrl($cookie->value);
                    // Удаляем cookie после использования
                    Yii::$app->response->cookies->remove('oauth_redirect_uri');
                }
            }
            
            // Если не нашли, используем frontendUrl из параметров
            if (!$frontendUrl) {
                $frontendUrl = $this->sanitizeFrontendUrl(Yii::$app->params['frontendUrl'] ?? null);
            }
            
            // Если все еще не нашли, пытаемся определить из homePage
            // Но не используем старый домен, если есть новый фронтенд
            if (!$frontendUrl) {
                $homePage = Yii::$app->params['homePage'] ?? 'http://localhost';
                // Если homePage содержит api., убираем его (это старый фронтенд)
                // Для нового фронтенда лучше использовать frontendUrl из конфигурации
                $frontendUrl = str_replace('api.', '', $homePage);
                // Локальный Next.js проект слушает штатный порт 3001.
                if (strpos($frontendUrl, 'localhost') !== false || strpos($frontendUrl, '127.0.0.1') !== false) {
                    $frontendUrl = 'http://localhost:3001';
                }
                $frontendUrl = $this->sanitizeFrontendUrl($frontendUrl);
            }
            if (!$frontendUrl) {
                throw new \RuntimeException('Frontend OAuth redirect URL is not configured or allowed');
            }
            
            $exchangeCode = Yii::$app->security->generateRandomString(48);
            $exchangeStored = Yii::$app->cache->set('auth_exchange_' . hash('sha256', $exchangeCode), [
                'token' => $accessToken,
                'refresh_token' => $refreshToken,
                'user_id' => (int)$user->id,
            ], 60);
            if (!$exchangeStored) {
                throw new \RuntimeException('Unable to persist OAuth exchange code');
            }
            $redirectUrl = rtrim($frontendUrl, '/') . '/auth/callback?' . http_build_query([
                'code' => $exchangeCode,
            ]);
            Yii::$app->response->headers->set('Referrer-Policy', 'no-referrer');
            
            return Yii::$app->response->redirect($redirectUrl);
        } catch (\Exception $e) {
            Yii::error('Steam auth handling error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'auth');
            return $this->errorResponse('OAUTH_ERROR', 'Ошибка при обработке авторизации Steam: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Редирект на Discord OAuth для привязки аккаунта (требуется JWT)
     */
    public function actionDiscord()
    {
        $user = $this->getCurrentUser();
        $clientId = Yii::$app->settings->get('discord_client_id');
        if (empty($clientId)) {
            return $this->errorResponse('NOT_CONFIGURED', 'Discord OAuth не настроен', [], 503);
        }
        $baseUrl = rtrim(Yii::$app->request->hostInfo, '/');
        $redirectUri = $baseUrl . '/v1/auth/discord-callback';
        $state = Yii::$app->security->generateRandomString(32);
        $returnUrl = $this->getLinkReturnUrl();
        Yii::$app->cache->set('discord_oauth_state_' . $state, ['state' => $state, 'user_id' => $user->id, 'return_url' => $returnUrl], 600);
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'identify',
            'state' => $state,
        ];
        $authUrl = 'https://discord.com/api/oauth2/authorize?' . http_build_query($params);
        Yii::$app->response->headers->set('Referrer-Policy', 'no-referrer');
        return $this->redirect($authUrl);
    }

    /**
     * Редирект на Twitch OAuth для привязки аккаунта (требуется JWT)
     */
    public function actionTwitch()
    {
        $user = $this->getCurrentUser();
        $clientId = Yii::$app->settings->get('twitch_client_id');
        if (empty($clientId)) {
            return $this->errorResponse('NOT_CONFIGURED', 'Twitch OAuth не настроен', [], 503);
        }
        $baseUrl = rtrim(Yii::$app->request->hostInfo, '/');
        $redirectUri = $baseUrl . '/v1/auth/twitch-callback';
        $state = Yii::$app->security->generateRandomString(32);
        $returnUrl = $this->getLinkReturnUrl();
        Yii::$app->cache->set('twitch_oauth_state_' . $state, ['state' => $state, 'user_id' => $user->id, 'return_url' => $returnUrl], 600);
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'user:read:email',
            'state' => $state,
        ];
        $authUrl = 'https://id.twitch.tv/oauth2/authorize?' . http_build_query($params);
        Yii::$app->response->headers->set('Referrer-Policy', 'no-referrer');
        return $this->redirect($authUrl);
    }

    /**
     * Редирект на Kick OAuth 2.1 (PKCE) для привязки аккаунта (требуется JWT)
     */
    public function actionKick()
    {
        $user = $this->getCurrentUser();
        $clientId = Yii::$app->settings->get('kick_client_id');
        if (empty($clientId)) {
            return $this->errorResponse('NOT_CONFIGURED', 'Kick OAuth не настроен', [], 503);
        }
        $baseUrl = rtrim(Yii::$app->request->hostInfo, '/');
        $redirectUri = $baseUrl . '/v1/auth/kick-callback';
        $state = Yii::$app->security->generateRandomString(32);
        $codeVerifier = Yii::$app->security->generateRandomString(64);
        $codeChallenge = strtr(rtrim(base64_encode(hash('sha256', $codeVerifier, true)), '='), '+/', '-_');
        $returnUrl = $this->getLinkReturnUrl();
        Yii::$app->cache->set('kick_oauth_state_' . $state, [
            'state' => $state,
            'user_id' => $user->id,
            'code_verifier' => $codeVerifier,
            'return_url' => $returnUrl,
        ], 600);
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
        Yii::$app->response->headers->set('Referrer-Policy', 'no-referrer');
        return $this->redirect($authUrl);
    }

    /**
     * URL, куда редиректить пользователя после привязки (Discord/Twitch/Kick).
     * Берётся из query return_url/redirect_uri, Referer или params['frontendUrl'], иначе из домена без api.
     */
    protected function getLinkReturnUrl()
    {
        $returnUrl = Yii::$app->request->get('return_url') ?: Yii::$app->request->get('redirect_uri');
        if (!empty($returnUrl) && preg_match('#^https?://#i', $returnUrl)) {
            return $returnUrl;
        }
        $referer = Yii::$app->request->getReferrer();
        if (!empty($referer)) {
            $apiHost = parse_url(rtrim(Yii::$app->request->hostInfo, '/'), PHP_URL_HOST);
            $refHost = parse_url($referer, PHP_URL_HOST);
            if ($refHost && $refHost !== $apiHost) {
                return rtrim($referer, '/');
            }
        }
        return \api\components\LinkReturnUrlHelper::getDefaultProfileUrl();
    }

    /**
     * Авторизация с JWT токеном (для проверки токена)
     * POST /api/v1/auth/login
     */
    public function actionLogin()
    {
        $token = Yii::$app->request->post('token');

        if (empty($token)) {
            return $this->errorResponse('INVALID_TOKEN', 'Токен не предоставлен', [], 400);
        }

        try {
            $jwtService = Yii::$app->get('jwt');
            $payload = $jwtService->validateToken($token);

            $userId = $jwtService->getUserId($payload);
            $user = User::findIdentity($userId);

            if (!$user) {
                return $this->errorResponse('USER_NOT_FOUND', 'Пользователь не найден', [], 404);
            }

            return $this->successResponse([
                'token' => $token,
                'expires_in' => Yii::$app->params['jwt']['expiration'] ?? 604800,
                'user' => $this->formatUser($user),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('INVALID_TOKEN', $e->getMessage(), [], 401);
        }
    }

    /**
     * Обновление JWT токена
     * 
     * @OA\Post(
     *     path="/v1/auth/refresh",
     *     operationId="authRefresh",
     *     tags={"Auth"},
     *     summary="Обновить JWT токен",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"refresh_token"},
     *                 @OA\Property(property="refresh_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGc...")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Новый токен",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(property="data", type="object",
     *                     @OA\Property(property="token", type="string"),
     *                     @OA\Property(property="expires_in", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Неверный refresh token")
     * )
     */
    public function actionRefresh()
    {
        $refreshToken = Yii::$app->request->post('refresh_token');

        if (empty($refreshToken)) {
            return $this->errorResponse('INVALID_TOKEN', 'Refresh токен не предоставлен', [], 400);
        }

        try {
            $jwtService = Yii::$app->get('jwt');
            $payload = $jwtService->validateToken($refreshToken);

            // Проверяем что это refresh токен
            if (!$jwtService->isRefreshToken($payload)) {
                return $this->errorResponse('INVALID_TOKEN', 'Токен не является refresh токеном', [], 400);
            }

            $userId = $jwtService->getUserId($payload);
            $steamId = $jwtService->getSteamId($payload);
            $user = User::findIdentity($userId);

            if (!$user) {
                return $this->errorResponse('USER_NOT_FOUND', 'Пользователь не найден', [], 404);
            }

            // Генерируем новые токены
            $newAccessToken = $jwtService->generateToken($user->id, $user->steam_id, false);
            $newRefreshToken = $jwtService->generateToken($user->id, $user->steam_id, true);

            // Добавляем старый refresh токен в blacklist
            $jti = $jwtService->getJti($payload);
            if ($jti) {
                $jwtAuthFilter = new JwtAuthFilter();
                $jwtAuthFilter->addToBlacklist($jti);
            }

            return $this->successResponse([
                'token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
                'expires_in' => Yii::$app->params['jwt']['expiration'] ?? 604800,
                'user' => $this->formatUser($user),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('INVALID_TOKEN', $e->getMessage(), [], 401);
        }
    }

    /**
     * Выход пользователя (инвалидация токена)
     * 
     * @OA\Post(
     *     path="/v1/auth/logout",
     *     operationId="authLogout",
     *     tags={"Auth"},
     *     summary="Выйти из системы",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Успешный выход",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(property="data", type="object",
     *                     @OA\Property(property="message", type="string", example="Вы успешно вышли")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionLogout()
    {
        $this->requireAuth();

        $token = Yii::$app->get('jwt')->extractTokenFromRequest(Yii::$app->request);

        if ($token) {
            try {
                $jwtService = Yii::$app->get('jwt');
                $payload = $jwtService->validateToken($token);
                $jti = $jwtService->getJti($payload);

                if ($jti) {
                    // Добавляем токен в blacklist
                    $jwtAuthFilter = new JwtAuthFilter();
                    $jwtAuthFilter->addToBlacklist($jti);
                }
            } catch (\Exception $e) {
                // Игнорируем ошибки валидации при logout
            }
        }

        Yii::$app->user->logout();

        // SteamAuth (Vikas5914) хранит steamid в $_SESSION['steamdata']. Пока это не сброшено,
        // следующий заход на /v1/auth/oauth считает пользователя уже залогиненным в Steam и
        // сразу выдаёт JWT — без экрана Steam (выглядит как «вход под старыми данными»).
        // Нужно явно открыть сессию: при XHR с Cookie сессия иначе может быть не загружена в $_SESSION.
        if (Yii::$app->has('session')) {
            Yii::$app->session->open();
        }
        if (isset($_SESSION['steamdata'])) {
            unset($_SESSION['steamdata']);
        }

        return $this->successResponse([
            'message' => 'Вы успешно вышли',
        ]);
    }

    /**
     * Получение текущего пользователя
     * 
     * @OA\Get(
     *     path="/v1/auth/me",
     *     operationId="getCurrentUser",
     *     tags={"Auth"},
     *     summary="Получить информацию о текущем пользователе",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="expand",
     *         in="query",
     *         required=false,
     *         description="Доп. блоки через запятую. balance — те же поля, что GET /v1/user/balance (внутри data.balances)",
     *         @OA\Schema(type="string", example="balance")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Информация о пользователе",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="success", type="boolean", example=true),
     *                 @OA\Property(property="data", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="username", type="string", example="user123"),
     *                     @OA\Property(property="steam_id", type="string", example="76561198000000001"),
     *                     @OA\Property(property="avatar", type="string", example="https://..."),
     *                     @OA\Property(property="roles", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionMe()
    {
        if (Yii::$app->user->isGuest) {
            return $this->errorResponse('UNAUTHORIZED', 'Authentication required', [], 401);
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;

        $data = $this->formatUser($user);

        $expand = Yii::$app->request->get('expand', '');
        $parts = array_filter(array_map('trim', explode(',', strtolower((string) $expand))));
        if (in_array('balance', $parts, true)) {
            $data['balances'] = $this->buildBalancesPayloadForMe($user);
        }

        return $this->successResponse($data);
    }

    /**
     * Лёгкий payload для корневого layout: профиль и только personal balance.
     * В отличие от expand=balance не вычисляет skins/referral данные.
     */
    public function actionSession()
    {
        if (Yii::$app->user->isGuest) {
            return $this->errorResponse('UNAUTHORIZED', 'Authentication required', [], 401);
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;
        $personalBalance = $user->getPersonalBalance();
        $data = $this->formatUser($user);
        $data['balances'] = [
            'personal' => [
                'balance' => (float)$personalBalance->balance,
                'balanceCeil' => (int)ceil($personalBalance->balance),
                'balanceFormat' => $personalBalance->getBalanceFormat(),
            ],
        ];

        Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
        return $this->successResponse($data);
    }

    /**
     * Однократно обменивает OAuth code на пару JWT. Код удаляется до выдачи
     * ответа, поэтому повторное воспроизведение невозможно.
     */
    public function actionExchange()
    {
        if (!Yii::$app->request->isPost) {
            return $this->errorResponse('METHOD_NOT_ALLOWED', 'POST required', [], 405);
        }
        $code = trim((string)Yii::$app->request->post('code', ''));
        if ($code === '' || strlen($code) > 128) {
            return $this->errorResponse('INVALID_CODE', 'Invalid or expired authorization code', [], 400);
        }

        $cacheKey = 'auth_exchange_' . hash('sha256', $code);
        $payload = $this->pullAuthExchangePayload($cacheKey);
        if (!is_array($payload) || empty($payload['token']) || empty($payload['refresh_token'])) {
            return $this->errorResponse('INVALID_CODE', 'Invalid or expired authorization code', [], 400);
        }

        Yii::$app->response->headers->set('Cache-Control', 'no-store');
        Yii::$app->response->headers->set('Referrer-Policy', 'no-referrer');
        return $this->successResponse([
            'token' => $payload['token'],
            'refresh_token' => $payload['refresh_token'],
        ]);
    }

    /**
     * Payload балансов как у {@see UserController::actionBalance()} (корень data ответа).
     *
     * @return array{personal: array, skins: array, referral: array}
     */
    protected function buildBalancesPayloadForMe(User $user): array
    {
        $personalBalance = $user->getPersonalBalance();
        $skinsBalance = $user->getSkinsBalance();
        $referralBalance = $user->getReferralBalance();

        return [
            'personal' => [
                'balance' => (float) $personalBalance->balance,
                'balanceCeil' => (int) ceil($personalBalance->balance),
                'balanceFormat' => $personalBalance->getBalanceFormat(),
            ],
            'skins' => [
                'balance' => (float) $skinsBalance->balance,
                'balanceCeil' => (int) ceil($skinsBalance->balance),
            ],
            'referral' => [
                'balance' => (float) $referralBalance,
            ],
        ];
    }

    /**
     * Создание пользователя из данных Steam
     * 
     * @param string $steamId Steam ID
     * @param array $attributes Атрибуты из Steam
     * @return User
     */
    protected function createUserFromSteam($steamId, $attributes)
    {
        $user = new User();
        $user->steam_id = $steamId;
        $user->username = $attributes['personaname'] ?? 'user_' . substr($steamId, -6);
        $user->email = $steamId . '@steam.local';
        $user->auth_key = Yii::$app->security->generateRandomString(32);
        $user->password_hash = Yii::$app->security->generatePasswordHash(Yii::$app->security->generateRandomString());
        $user->ref_code = rand(10000, 99999);
        $user->socket_room = Yii::$app->security->generateRandomString(32);
        $user->status = User::STATUS_ACTIVE;
        $user->save(false);

        // Создаем профиль пользователя
        if ($user->userProfile) {
            $user->userProfile->name = $user->username;
            if (!empty($attributes['avatarfull'])) {
                $user->userProfile->steam_avatar_url = $attributes['avatarfull'];
            }
            $user->userProfile->save(false);
        }

        $this->attachReferralParentForNewUser($user);

        return $user;
    }

    /**
     * Обновление пользователя из данных Steam
     * 
     * @param User $user
     * @param array $attributes Атрибуты из Steam
     */
    protected function updateUserFromSteam($user, $attributes)
    {
        if (!empty($attributes['personaname']) && $attributes['personaname'] !== $user->username) {
            $user->username = $attributes['personaname'];
            $user->save(false);
        }

        if ($user->userProfile) {
            if (!empty($attributes['personaname']) && $attributes['personaname'] !== $user->userProfile->name) {
                $user->userProfile->name = $attributes['personaname'];
            }
            if (!empty($attributes['avatarfull']) && $attributes['avatarfull'] !== $user->userProfile->steam_avatar_url) {
                $user->userProfile->steam_avatar_url = $attributes['avatarfull'];
            }
            $user->userProfile->save(false);
        }
    }

    /**
     * Нормализация реферального кода (цифры, как в /p/{refCode}).
     */
    protected function sanitizeReferralRefCode($raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', (string)$raw);

        return $digits !== '' ? $digits : null;
    }

    /**
     * Разрешает OAuth redirect только на origin из CORS allowlist. Путь,
     * query и fragment намеренно отбрасываются: возврат внутри сайта хранит
     * сам frontend в sessionStorage.
     */
    protected function sanitizeFrontendUrl($raw): ?string
    {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $parts = parse_url(trim($raw));
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        $origin = $scheme . '://' . strtolower($parts['host']);
        if (!empty($parts['port'])) {
            $origin .= ':' . (int)$parts['port'];
        }
        $allowed = array_map(static function ($value) {
            return rtrim(strtolower((string)$value), '/');
        }, $this->getAllowedOrigins());

        return in_array(strtolower($origin), $allowed, true) ? $origin : null;
    }

    /**
     * Atomically reads and removes an OAuth exchange code when Redis backs the
     * Yii cache. Other cache drivers retain the compatible get/delete fallback.
     */
    private function pullAuthExchangePayload(string $cacheKey)
    {
        $cache = Yii::$app->cache;
        if ($cache instanceof \yii\redis\Cache) {
            $script = <<<'LUA'
local value = redis.call('GET', KEYS[1])
if value then
    redis.call('DEL', KEYS[1])
end
return value
LUA;
            $raw = $cache->redis->executeCommand('EVAL', [
                $script,
                1,
                $cache->buildKey($cacheKey),
            ]);
            if ($raw === null || $raw === false) {
                return false;
            }
            if ($cache->serializer === false) {
                return $raw;
            }
            $wrapped = $cache->serializer === null
                ? @unserialize((string)$raw, ['allowed_classes' => false])
                : call_user_func($cache->serializer[1], $raw);

            return is_array($wrapped) && array_key_exists(0, $wrapped)
                ? $wrapped[0]
                : false;
        }

        $payload = $cache->get($cacheKey);
        $cache->delete($cacheKey);
        return $payload;
    }

    /**
     * refCode из cookie (Next.js /p/ не подписан Yii) и резервно из сессии OAuth.
     */
    protected function getReferralRefCodeForNewUser(): ?string
    {
        $fromCookie = $this->sanitizeReferralRefCode($_COOKIE['refCode'] ?? null)
            ?: $this->sanitizeReferralRefCode(Yii::$app->request->cookies->getValue('refCode'));
        if ($fromCookie !== null) {
            return $fromCookie;
        }
        if (!empty($_SESSION['oauth_ref_code'])) {
            return $this->sanitizeReferralRefCode($_SESSION['oauth_ref_code']);
        }

        return null;
    }

    /**
     * Привязка нового пользователя к рефереру (user_tree), как в common/controllers/AuthController::onAuthSuccess.
     */
    protected function attachReferralParentForNewUser(User $user): void
    {
        /** @var int Корневой родитель по умолчанию (legacy) */
        $defaultRootParentId = 509;

        $refCode = $this->getReferralRefCodeForNewUser();
        $parentUserId = $defaultRootParentId;

        if ($refCode !== null) {
            $parentUser = User::findByRefCode($refCode);
            if ($parentUser && (int)$parentUser->id !== (int)$user->id) {
                $parentUserId = (int)$parentUser->id;
                if (!empty($parentUser->telegram_chat_id) && Yii::$app->has('personalBotTelegram')) {
                    try {
                        Yii::$app->personalBotTelegram->sendMessage(
                            $parentUser->telegram_chat_id,
                            "По вашей ссылке зарегистировался новый пользователь.\nПользователь: {$user->steam_id}"
                        );
                    } catch (\Throwable $e) {
                        Yii::warning('Referral telegram notify failed: ' . $e->getMessage(), 'auth');
                    }
                }
            }
            WebCookie::remove('refCode');
        }

        UserTree::appendUser((int)$user->id, $parentUserId);
    }

    /**
     * Форматирование данных пользователя для API
     */
    protected function formatUser(User $user)
    {
        $data = [
            'id' => $user->id,
            'username' => $user->username,
            'steam_id' => $user->steam_id,
            'avatar' => $user->getAvatar() ?? '',
            'roles' => $user->getUserRoles(),
            'isAdmin' => $user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT]),
            'created_at' => $user->created_at,
            'is_email' => (bool)$user->is_email,
            'email' => $user->is_email ? ($user->email ?? '') : '',
        ];

        // Добавляем информацию о VIP, если он активен
        $activeVip = $user->getActiveVip();
        if ($activeVip) {
            $data['activeVip'] = [
                'expires_at' => $activeVip->expires_at,
                'timestamp' => strtotime($activeVip->expires_at),
            ];
        } else {
            $data['activeVip'] = null;
        }

        // Добавляем информацию о сервере, на котором играет игрок
        $server = $user->server;
        if ($server) {
            $data['server'] = [
                'id' => $server->id,
                'tag' => $server->tag,
                'name' => $server->name,
                'monitoring_name' => $server->monitoring_name,
            ];
        } else {
            $data['server'] = null;
        }

        // IP пользователя и страна по GeoIP (как в старой версии)
        $data['ip'] = !empty($user->ip) ? $user->ip : null;
        $data['country_code'] = null;
        if (!empty($user->ip)) {
            try {
                $data['country_code'] = $user->getCountryByIp();
            } catch (\Exception $e) {
                // не показываем страну при ошибке GeoIP
            }
        }

        // Последнее посещение сервера
        $data['last_visit_server_at'] = !empty($user->last_visit_server_at) ? $user->last_visit_server_at : null;

        return $data;
    }

    /**
     * Вход под другим пользователем (только для админов)
     * 
     * @OA\Post(
     *     path="/v1/auth/impersonate",
     *     operationId="authImpersonate",
     *     tags={"Auth"},
     *     summary="Войти под другим пользователем",
     *     description="Позволяет админам войти под другим пользователем по Steam ID",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"steam_id"},
     *             @OA\Property(property="steam_id", type="string", example="76561198012345678", description="Steam ID пользователя")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успешный вход под пользователем",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="token", type="string", description="JWT access token"),
     *                 @OA\Property(property="refresh_token", type="string", description="JWT refresh token")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=403, description="Доступ запрещен (не админ)"),
     *     @OA\Response(response=404, description="Пользователь не найден")
     * )
     */
    public function actionImpersonate()
    {
        $this->requireAuth();
        
        // Проверяем, что текущий пользователь является админом
        $currentUser = Yii::$app->user->identity;
        if (!$currentUser || !$currentUser->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT])) {
            throw new ForbiddenHttpException('Только администраторы могут входить под другими пользователями');
        }

        $steamId = Yii::$app->request->post('steam_id');
        if (empty($steamId)) {
            throw new BadRequestHttpException('Steam ID обязателен');
        }

        // Находим пользователя по Steam ID
        $targetUser = User::findOne(['steam_id' => $steamId]);
        if (!$targetUser) {
            throw new NotFoundHttpException('Пользователь с таким Steam ID не найден');
        }

        // Генерируем JWT токены для целевого пользователя
        $jwtService = Yii::$app->get('jwt');
        $accessToken = $jwtService->generateToken($targetUser->id, $targetUser->steam_id, false);
        $refreshToken = $jwtService->generateToken($targetUser->id, $targetUser->steam_id, true);

        Yii::info("Admin {$currentUser->id} ({$currentUser->username}) impersonated user {$targetUser->id} ({$targetUser->username})", 'auth');

        return $this->successResponse([
            'token' => $accessToken,
            'refresh_token' => $refreshToken,
        ]);
    }
}



















