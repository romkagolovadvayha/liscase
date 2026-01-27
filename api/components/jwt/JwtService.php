<?php

namespace api\components\jwt;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Yii;
use yii\base\Component;
use yii\base\Exception;

/**
 * Сервис для работы с JWT токенами
 * 
 * Использует firebase/php-jwt для генерации и валидации токенов
 */
class JwtService extends Component
{
    /**
     * @var string Секретный ключ для подписи JWT (должен быть в переменных окружения)
     */
    public $secret;

    /**
     * @var string Алгоритм подписи (HS256, RS256 и т.д.)
     */
    public $algorithm = 'HS256';

    /**
     * @var int Время жизни access токена в секундах (по умолчанию 1 час)
     */
    public $expiration = 3600;

    /**
     * @var int Время жизни refresh токена в секундах (по умолчанию 7 дней)
     */
    public $refreshExpiration = 604800;

    /**
     * @var string Идентификатор приложения (issuer)
     */
    public $issuer = 'liscase-api';

    /**
     * @var string Аудитория токена (audience)
     */
    public $audience = 'liscase-frontend';

    /**
     * Инициализация компонента
     */
    public function init()
    {
        parent::init();

        // Получаем секрет из конфигурации или переменных окружения
        if (empty($this->secret)) {
            $this->secret = Yii::$app->params['jwt']['secret'] ?? getenv('JWT_SECRET');
        }

        if (empty($this->secret)) {
            throw new Exception('JWT secret key is not configured. Set it in params or JWT_SECRET environment variable.');
        }

        // Минимальная длина секрета для HS256 - 32 символа (256 бит)
        if (strlen($this->secret) < 32) {
            Yii::warning('JWT secret key is too short. Recommended minimum length is 32 characters (256 bits).', 'jwt');
        }
    }

    /**
     * Генерация JWT токена для пользователя
     * 
     * @param int $userId ID пользователя
     * @param string $steamId Steam ID пользователя
     * @param bool $isRefreshToken Генерировать refresh токен?
     * @return string JWT токен
     */
    public function generateToken($userId, $steamId, $isRefreshToken = false)
    {
        $now = time();
        $expiration = $isRefreshToken ? $this->refreshExpiration : $this->expiration;
        
        $payload = [
            'iss' => $this->issuer,           // Issuer
            'aud' => $this->audience,          // Audience
            'iat' => $now,                     // Issued at
            'exp' => $now + $expiration,       // Expiration
            'jti' => $this->generateJti($userId, $now), // JWT ID (уникальный идентификатор)
            'user_id' => (int)$userId,
            'steam_id' => $steamId,
            'type' => $isRefreshToken ? 'refresh' : 'access',
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Валидация и декодирование JWT токена
     * 
     * @param string $token JWT токен
     * @return array Декодированный payload
     * @throws ExpiredException Если токен истек
     * @throws SignatureInvalidException Если подпись неверна
     * @throws Exception Если токен невалиден
     */
    public function validateToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            
            // Преобразуем объект в массив
            $payload = (array)$decoded;

            // Дополнительная валидация
            $this->validatePayload($payload);

            return $payload;
        } catch (ExpiredException $e) {
            throw new Exception('JWT token has expired', 401, $e);
        } catch (SignatureInvalidException $e) {
            throw new Exception('JWT token signature is invalid', 401, $e);
        } catch (\Exception $e) {
            throw new Exception('JWT token is invalid: ' . $e->getMessage(), 401, $e);
        }
    }

    /**
     * Валидация payload токена
     * 
     * @param array $payload
     * @throws Exception
     */
    protected function validatePayload($payload)
    {
        // Проверка issuer
        if (isset($payload['iss']) && $payload['iss'] !== $this->issuer) {
            throw new Exception('JWT token issuer is invalid');
        }

        // Проверка audience
        if (isset($payload['aud']) && $payload['aud'] !== $this->audience) {
            throw new Exception('JWT token audience is invalid');
        }

        // Проверка наличия обязательных полей
        if (empty($payload['user_id']) || empty($payload['steam_id'])) {
            throw new Exception('JWT token payload is incomplete');
        }
    }

    /**
     * Генерация уникального JTI (JWT ID)
     * 
     * @param int $userId ID пользователя
     * @param int $timestamp Временная метка
     * @return string Уникальный идентификатор токена
     */
    protected function generateJti($userId, $timestamp)
    {
        $random = bin2hex(random_bytes(16));
        return hash('sha256', $userId . $timestamp . $random);
    }

    /**
     * Извлечение токена из заголовка Authorization
     * 
     * @param \yii\web\Request $request
     * @return string|null Токен или null если не найден
     */
    public function extractTokenFromRequest($request)
    {
        $authHeader = $request->headers->get('Authorization');
        
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Проверка, является ли токен refresh токеном
     * 
     * @param array $payload Декодированный payload
     * @return bool
     */
    public function isRefreshToken($payload)
    {
        return isset($payload['type']) && $payload['type'] === 'refresh';
    }

    /**
     * Получение user_id из токена
     * 
     * @param array $payload Декодированный payload
     * @return int|null
     */
    public function getUserId($payload)
    {
        return isset($payload['user_id']) ? (int)$payload['user_id'] : null;
    }

    /**
     * Получение steam_id из токена
     * 
     * @param array $payload Декодированный payload
     * @return string|null
     */
    public function getSteamId($payload)
    {
        return isset($payload['steam_id']) ? $payload['steam_id'] : null;
    }

    /**
     * Получение JTI из токена
     * 
     * @param array $payload Декодированный payload
     * @return string|null
     */
    public function getJti($payload)
    {
        return isset($payload['jti']) ? $payload['jti'] : null;
    }
}





