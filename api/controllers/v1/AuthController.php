<?php

namespace api\controllers\v1;

use Yii;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;
use yii\web\Cookie;
use yii\authclient\OpenId;
use common\models\user\User;
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
            'except' => ['oauth', 'callback', 'login', 'refresh', 'options'],
            'throwException' => false, // Не выбрасывать исключение, просто не авторизовывать
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
        $redirectUri = Yii::$app->request->get('redirect_uri');
        if ($redirectUri) {
            $_SESSION['oauth_redirect_uri'] = $redirectUri;
            // Также сохраняем в cookie как резервный способ (на 10 минут)
            Yii::$app->response->cookies->add(new Cookie([
                'name' => 'oauth_redirect_uri',
                'value' => $redirectUri,
                'expire' => time() + 600,
                'httpOnly' => false, // Доступен из JavaScript для отладки
            ]));
        } else {
            // Если не передан, пробуем определить из Referer
            $referer = Yii::$app->request->getReferrer();
            if ($referer) {
                // Извлекаем базовый URL из Referer
                $parsed = parse_url($referer);
                if ($parsed && isset($parsed['scheme']) && isset($parsed['host'])) {
                    $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
                    $redirectUri = $parsed['scheme'] . '://' . $parsed['host'] . $port;
                    $_SESSION['oauth_redirect_uri'] = $redirectUri;
                    Yii::$app->response->cookies->add(new Cookie([
                        'name' => 'oauth_redirect_uri',
                        'value' => $redirectUri,
                        'expire' => time() + 600,
                        'httpOnly' => false,
                    ]));
                }
            }
        }
        
        $config = [
            'apikey' => Yii::$app->settings->get('steam_apiKey'), // Steam API KEY
            'domainname' => Yii::$app->params['homePage'] . '/', // Displayed domain in the login-screen
            'loginpage' => Yii::$app->params['homePage'] . '/v1/auth/callback', // Returns to callback page
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
        $config = [
            'apikey' => Yii::$app->settings->get('steam_apiKey'),
            'domainname' => Yii::$app->params['homePage'] . '/',
            'loginpage' => Yii::$app->params['homePage'] . '/v1/auth/callback',
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

            // Генерируем JWT токены
            $jwtService = Yii::$app->get('jwt');
            $accessToken = $jwtService->generateToken($user->id, $user->steam_id, false);
            $refreshToken = $jwtService->generateToken($user->id, $user->steam_id, true);

            // Редирект на фронтенд с токенами в URL параметрах
            // Используем сохраненный redirect_uri из сессии/cookie, или frontendUrl из параметров, или определяем из homePage
            $frontendUrl = null;
            
            // Сначала пробуем использовать сохраненный redirect_uri из сессии
            if (isset($_SESSION['oauth_redirect_uri']) && !empty($_SESSION['oauth_redirect_uri'])) {
                $frontendUrl = $_SESSION['oauth_redirect_uri'];
                unset($_SESSION['oauth_redirect_uri']); // Удаляем из сессии после использования
            }
            
            // Если не нашли в сессии, пробуем из cookie (резервный способ)
            if (!$frontendUrl) {
                $cookie = Yii::$app->request->cookies->get('oauth_redirect_uri');
                if ($cookie && !empty($cookie->value)) {
                    $frontendUrl = $cookie->value;
                    // Удаляем cookie после использования
                    Yii::$app->response->cookies->remove('oauth_redirect_uri');
                }
            }
            
            // Если не нашли, используем frontendUrl из параметров
            if (!$frontendUrl) {
                $frontendUrl = Yii::$app->params['frontendUrl'] ?? null;
            }
            
            // Если все еще не нашли, пытаемся определить из homePage
            // Но не используем старый домен, если есть новый фронтенд
            if (!$frontendUrl) {
                $homePage = Yii::$app->params['homePage'] ?? 'http://localhost';
                // Если homePage содержит api., убираем его (это старый фронтенд)
                // Для нового фронтенда лучше использовать frontendUrl из конфигурации
                $frontendUrl = str_replace('api.', '', $homePage);
                // Если это localhost, используем порт 3000
                if (strpos($frontendUrl, 'localhost') !== false || strpos($frontendUrl, '127.0.0.1') !== false) {
                    $frontendUrl = 'http://localhost:3000';
                }
            }
            
            $redirectUrl = $frontendUrl . '/auth/callback?' . http_build_query([
                'token' => $accessToken,
                'refresh_token' => $refreshToken,
            ]);
            
            return Yii::$app->response->redirect($redirectUrl);
        } catch (\Exception $e) {
            Yii::error('Steam auth handling error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'auth');
            return $this->errorResponse('OAUTH_ERROR', 'Ошибка при обработке авторизации Steam: ' . $e->getMessage(), [], 500);
        }
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
                'expires_in' => Yii::$app->params['jwt']['expiration'] ?? 3600,
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
                'expires_in' => Yii::$app->params['jwt']['expiration'] ?? 3600,
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
        $this->requireAuth();

        $user = Yii::$app->user->identity;

        return $this->successResponse($this->formatUser($user));
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



















