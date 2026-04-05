<?php

namespace api\components\jwt;

use Yii;
use yii\base\ActionFilter;
use yii\web\UnauthorizedHttpException;
use yii\web\IdentityInterface;
use common\models\user\User;
use api\components\jwt\JwtService;

/**
 * Фильтр для проверки JWT авторизации
 * 
 * Автоматически проверяет JWT токен из заголовка Authorization
 * и устанавливает пользователя в Yii::$app->user
 */
class JwtAuthFilter extends ActionFilter
{
    /**
     * @var string Сообщение об ошибке при отсутствии токена
     */
    public $errorMessage = 'Authentication required';

    /**
     * @var bool Выбрасывать исключение при отсутствии токена (true) или просто не авторизовывать (false)
     */
    public $throwException = true;

    /**
     * Имена query-параметров с JWT (только для редких случаев: редирект из браузера без заголовка Authorization).
     * Задаётся в behaviors контроллера; по умолчанию пусто — токен только из Authorization.
     *
     * @var string[]
     */
    public $queryTokenParams = [];

    /**
     * @var JwtService Сервис для работы с JWT
     */
    protected $jwtService;

    /**
     * Инициализация фильтра
     */
    public function init()
    {
        parent::init();

        // Получаем JwtService из контейнера или создаем новый
        if (Yii::$app->has('jwt')) {
            $this->jwtService = Yii::$app->get('jwt');
        } else {
            $this->jwtService = new JwtService();
        }
    }

    /**
     * Выполняется перед action
     * 
     * @param \yii\base\Action $action
     * @return bool
     * @throws UnauthorizedHttpException
     */
    public function beforeAction($action)
    {
        // Пропускаем OPTIONS запросы (preflight для CORS)
        if (Yii::$app->request->getMethod() === 'OPTIONS') {
            return true;
        }

        $token = $this->jwtService->extractTokenFromRequest(Yii::$app->request);

        if (empty($token) && !empty($this->queryTokenParams)) {
            foreach ($this->queryTokenParams as $param) {
                $fromQuery = Yii::$app->request->get($param);
                if (is_string($fromQuery) && $fromQuery !== '') {
                    $token = $fromQuery;
                    break;
                }
            }
        }

        if (empty($token)) {
            if ($this->throwException) {
                throw new UnauthorizedHttpException($this->errorMessage);
            }
            return true; // Продолжаем выполнение без авторизации
        }

        try {
            // Валидация токена
            $payload = $this->jwtService->validateToken($token);

            // Проверка blacklist (если реализован)
            if ($this->isTokenBlacklisted($payload)) {
                throw new UnauthorizedHttpException('Token has been revoked');
            }

            // Поиск пользователя
            $user = $this->findUser($payload);

            if (!$user) {
                throw new UnauthorizedHttpException('User not found');
            }

            // Установка пользователя в Yii::$app->user
            Yii::$app->user->login($user, 0); // 0 = не использовать cookie, только сессию для текущего запроса

            return true;
        } catch (\Exception $e) {
            if ($this->throwException) {
                throw new UnauthorizedHttpException($e->getMessage());
            }
            return true; // Продолжаем выполнение без авторизации
        }
    }

    /**
     * Поиск пользователя по данным из токена
     * 
     * @param array $payload Декодированный payload токена
     * @return User|IdentityInterface|null
     */
    protected function findUser($payload)
    {
        $userId = $this->jwtService->getUserId($payload);
        $steamId = $this->jwtService->getSteamId($payload);

        if (empty($userId) && empty($steamId)) {
            return null;
        }

        // Ищем по user_id (приоритет) или steam_id
        if ($userId) {
            $user = User::findIdentity($userId);
            if ($user) {
                return $user;
            }
        }

        if ($steamId) {
            $user = User::find()->where(['steam_id' => $steamId])->one();
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Проверка, находится ли токен в blacklist
     * 
     * @param array $payload Декодированный payload токена
     * @return bool
     */
    protected function isTokenBlacklisted($payload)
    {
        $jti = $this->jwtService->getJti($payload);
        
        if (empty($jti)) {
            return false;
        }

        // Проверка в кэше/Redis (если blacklist реализован)
        $cacheKey = 'jwt_blacklist_' . $jti;
        $blacklisted = Yii::$app->cache->get($cacheKey);

        return $blacklisted !== false;
    }

    /**
     * Добавление токена в blacklist
     * 
     * @param string $jti JWT ID токена
     * @param int $ttl Время жизни в blacklist (по умолчанию до истечения токена)
     */
    public function addToBlacklist($jti, $ttl = null)
    {
        if (empty($jti)) {
            return;
        }

        $cacheKey = 'jwt_blacklist_' . $jti;
        
        // Если TTL не указан, используем время жизни токена (максимум 7 дней для refresh)
        if ($ttl === null) {
            $ttl = 604800; // 7 дней
        }

        Yii::$app->cache->set($cacheKey, true, $ttl);
    }
}




