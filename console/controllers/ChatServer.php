<?php
namespace console\controllers;

use common\components\queue\process\ReturnDropJob;
use common\components\queue\support\BeforeMessageJob;
use common\components\queue\support\OpenAiJob;
use common\models\box\DropBlocked;
use common\models\rcon\RconTasks;
use common\models\support\SupportFile;
use common\models\support\SupportMessage;
use common\models\support\SupportRead;
use common\models\user\UserDrop;
use common\models\profit\Profit;
use Yii;
use common\components\helpers\Role;
use common\models\support\Support;
use common\models\user\User;
use consik\yii2websocket\events\WSClientEvent;
use consik\yii2websocket\WebSocketServer;
use Ratchet\ConnectionInterface;
use yii\base\BaseObject;
use yii\db\Exception as DbException;
use PDOException;
use Psr\Http\Message\RequestInterface;

class ChatServer extends WebSocketServer
{
    /** @var ChatServer Singleton instance */
    private static $instance = null;

    /** @var int секунд без активности до закрытия */
    private $idleCloseSeconds = 45; // NEW

    /** @var int секунд на авторизацию после подключения */
    private $authTimeoutSeconds = 30; // NEW

    /** @var int Максимальное количество подключений с одного IP */
    private $maxConnectionsPerIp = 100; // NEW

    /** @var int Максимальное количество подключений в секунду с одного IP */
    private $maxConnectionsPerSecond = 30; // NEW

    /** @var array Счетчик подключений по IP */
    private $connectionsByIp = []; // NEW

    /** @var array Время последнего подключения по IP */
    private $lastConnectionTimeByIp = []; // NEW

    /** @var array Индекс клиентов по user_id для быстрого поиска */
    private $clientsByUserId = [];

    /** @var array Индекс клиентов по chat для быстрого поиска */
    private $clientsByChat = [];

    /** @var int Смещение для чанковой обработки клиентов в таймере (чтобы не блокировать цикл) */
    private $supportTimerOffset = 0;

    /** @var int Смещение для чанковой отправки ping (не блокировать цикл) */
    private $pingTimerOffset = 0;

    /** @var int Смещение для таймера balance/drops (тяжёлый — отдельно от support, чтобы не блокировать чат) */
    private $balanceTimerOffset = 0;

    /** Макс. клиентов за один тик support-таймера — меньше = быстрее обрабатываются входящие (чат) */
    private const SUPPORT_TIMER_CHUNK = 20;

    /** Макс. клиентов за один тик ping-таймера */
    private const PING_TIMER_CHUNK = 80;

    /** Макс. вызовов commandBuyDrop за один тик (рендер тяжёлый — не блокировать цикл) */
    private const SUPPORT_TIMER_BUY_DROP_PER_TICK = 1;

    /** Кеш массива клиентов для таймера (инвалидируется при connect/disconnect) */
    private $clientsArrayCache = null;

    /** Счётчики за последнюю минуту (для отчёта раз в 60 сек) */
    private $statsChatSent = 0;
    private $statsSubscription = 0;
    private $statsSupportTicks = 0;
    private $statsPingTicks = 0;

    /**
     * Получить singleton инстанс сервера
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    private function log($m) { // NEW
        echo date('Y-m-d H:i:s') . " [WS] {$m}" . PHP_EOL;
    }

    /**
     * Переопределяем onOpen для получения реального IP из HTTP заголовков
     * @param ConnectionInterface $conn
     * @param RequestInterface|null $request HTTP запрос (может быть null в некоторых случаях)
     */
    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null)
    {
        $realIp = $conn->remoteAddress;

        // Attempt to get RequestInterface if not directly passed (e.g., from parent class)
        if (!$request) {
            try {
                $reflection = new \ReflectionObject($conn);

                // Try to find request in connection object properties
                $possibleProperties = ['httpRequest', '_httpRequest', 'request', '_request', 'WebSocket', 'ws'];
                foreach ($possibleProperties as $propName) {
                    if ($reflection->hasProperty($propName)) {
                        $prop = $reflection->getProperty($propName);
                        $prop->setAccessible(true);
                        $value = $prop->getValue($conn);

                        // Check if it's a RequestInterface
                        if ($value instanceof RequestInterface) {
                            $request = $value;
                            break;
                        }

                        // Check if it's an object with httpRequest property
                        if (is_object($value)) {
                            $valueReflection = new \ReflectionObject($value);
                            if ($valueReflection->hasProperty('httpRequest')) {
                                $httpRequestProp = $valueReflection->getProperty('httpRequest');
                                $httpRequestProp->setAccessible(true);
                                $httpRequestValue = $httpRequestProp->getValue($value);
                                if ($httpRequestValue instanceof RequestInterface) {
                                    $request = $httpRequestValue;
                                    break;
                                }
                            }
                        }
                    }
                }

                // If still not found, try to get all properties and search recursively
                if (!$request) {
                    $properties = $reflection->getProperties();
                    foreach ($properties as $prop) {
                        $prop->setAccessible(true);
                        $value = $prop->getValue($conn);
                        if ($value instanceof RequestInterface) {
                            $request = $value;
                            break;
                        }
                    }
                }

                // Try to get from parent class properties
                if (!$request) {
                    try {
                        $parentClass = $reflection->getParentClass();
                        if ($parentClass) {
                            $parentProps = $parentClass->getProperties();
                            foreach ($parentProps as $prop) {
                                $prop->setAccessible(true);
                                $value = $prop->getValue($conn);
                                if ($value instanceof RequestInterface) {
                                    $request = $value;
                                    break;
                                }
                            }
                        }
                    } catch (\Throwable $parentEx) {
                        // Ignore parent class reflection errors
                    }
                }

                // Try to find request in wrappedConn->WebSocket->request (most common case)
                if (!$request) {
                    try {
                        $props = $reflection->getProperties();
                        foreach ($props as $prop) {
                            $prop->setAccessible(true);
                            $propName = $prop->getName();
                            $propValue = $prop->getValue($conn);
                            
                            if (is_object($propValue) && $propName === 'wrappedConn') {
                                try {
                                    $wrappedReflection = new \ReflectionObject($propValue);
                                    $wrappedProps = $wrappedReflection->getProperties();
                                    foreach ($wrappedProps as $wrappedProp) {
                                        $wrappedProp->setAccessible(true);
                                        $wrappedPropName = $wrappedProp->getName();
                                        $wrappedValue = $wrappedProp->getValue($propValue);
                                        
                                        if ($wrappedValue instanceof RequestInterface) {
                                            $request = $wrappedValue;
                                            break 2;
                                        }
                                        
                                        // Check WebSocket object
                                        if (is_object($wrappedValue) && $wrappedPropName === 'WebSocket') {
                                            try {
                                                $nestedReflection = new \ReflectionObject($wrappedValue);
                                                $requestPropNames = ['request', 'httpRequest', '_request', '_httpRequest'];
                                                foreach ($requestPropNames as $reqPropName) {
                                                    if ($nestedReflection->hasProperty($reqPropName)) {
                                                        $reqProp = $nestedReflection->getProperty($reqPropName);
                                                        $reqProp->setAccessible(true);
                                                        $reqValue = $reqProp->getValue($wrappedValue);
                                                        
                                                        if ($reqValue instanceof RequestInterface) {
                                                            $request = $reqValue;
                                                            break 3;
                                                        } elseif (is_object($reqValue)) {
                                                            // Check if it has getHeader method (e.g., Guzzle\Http\Message\EntityEnclosingRequest)
                                                            try {
                                                                $reqValueReflection = new \ReflectionObject($reqValue);
                                                                if ($reqValueReflection->hasMethod('getHeader')) {
                                                                    $request = $reqValue;
                                                                    break 3;
                                                                }
                                                            } catch (\Throwable $reqValueEx) {
                                                                // Ignore
                                                            }
                                                        }
                                                    }
                                                }
                                                
                                                // If it's stdClass, use get_object_vars
                                                if ($wrappedValue instanceof \stdClass) {
                                                    $wsProps = get_object_vars($wrappedValue);
                                                    if (isset($wsProps['request'])) {
                                                        $requestValue = $wsProps['request'];
                                                        if ($requestValue instanceof RequestInterface) {
                                                            $request = $requestValue;
                                                            break 2;
                                                        } elseif (is_object($requestValue)) {
                                                            try {
                                                                $requestReflection = new \ReflectionObject($requestValue);
                                                                if ($requestReflection->hasMethod('getHeader')) {
                                                                    $request = $requestValue;
                                                                    break 2;
                                                                }
                                                            } catch (\Throwable $reqEx) {
                                                                // Ignore
                                                            }
                                                        }
                                                    }
                                                }
                                            } catch (\Throwable $nestedEx) {
                                                // Ignore nested reflection errors
                                            }
                                        }
                                    }
                                } catch (\Throwable $wrappedEx) {
                                    // Ignore wrapped reflection errors
                                }
                            }
                        }
                    } catch (\Throwable $dumpEx) {
                        // Ignore property dump errors
                    }
                }
            } catch (\Throwable $reflectionEx) {
                // Ignore reflection errors
            }
        }

        if ($request) {
            try {
                // Helper function to extract header value from various formats
                $extractHeaderValue = function($headerValue) {
                    if (empty($headerValue)) {
                        return null;
                    }
                    
                    // If it's an array, get first element
                    if (is_array($headerValue)) {
                        if (!empty($headerValue[0])) {
                            return trim($headerValue[0]);
                        }
                        return null;
                    }
                    
                    // If it's a string, use it directly
                    if (is_string($headerValue)) {
                        return trim($headerValue);
                    }
                    
                    // If it's an object, try to convert to string
                    if (is_object($headerValue)) {
                        // Try __toString() method
                        if (method_exists($headerValue, '__toString')) {
                            return trim((string)$headerValue);
                        }
                        
                        // Try to access value property
                        if (property_exists($headerValue, 'value')) {
                            $value = $headerValue->value;
                            if (is_string($value)) {
                                return trim($value);
                            }
                            if (is_array($value) && !empty($value[0])) {
                                return trim($value[0]);
                            }
                        }
                        
                        // Try to get first element if it's iterable
                        if (is_iterable($headerValue)) {
                            foreach ($headerValue as $val) {
                                if (is_string($val)) {
                                    return trim($val);
                                }
                            }
                        }
                        
                        // Try reflection to access internal properties
                        try {
                            $headerReflection = new \ReflectionObject($headerValue);
                            $props = $headerReflection->getProperties();
                            foreach ($props as $prop) {
                                $prop->setAccessible(true);
                                $propValue = $prop->getValue($headerValue);
                                if (is_string($propValue)) {
                                    return trim($propValue);
                                }
                                if (is_array($propValue) && !empty($propValue[0])) {
                                    return trim($propValue[0]);
                                }
                            }
                        } catch (\Throwable $refEx) {
                            // Ignore reflection errors
                        }
                        
                        // Last resort: try to serialize and extract
                        $serialized = serialize($headerValue);
                        if (preg_match('/s:\d+:"([^"]+)"/', $serialized, $matches)) {
                            return trim($matches[1]);
                        }
                    }
                    
                    return null;
                };
                
                // Try getHeaderLine() first (returns string, PSR-7 standard)
                $xRealIpValue = null;
                if (method_exists($request, 'getHeaderLine')) {
                    try {
                        $xRealIpValue = $request->getHeaderLine('X-Real-IP');
                        if (!empty($xRealIpValue)) {
                            $xRealIpValue = trim($xRealIpValue);
                        }
                    } catch (\Throwable $e) {
                        // Ignore
                    }
                }
                
                // If getHeaderLine didn't work, try getHeader()
                if (empty($xRealIpValue)) {
                    try {
                        $xRealIp = $request->getHeader('X-Real-IP');
                        $xRealIpValue = $extractHeaderValue($xRealIp);
                    } catch (\Throwable $e) {
                        // Ignore
                    }
                }
                
                // Process X-Real-IP value
                if (!empty($xRealIpValue)) {
                    $candidateIp = $xRealIpValue;
                    if ($candidateIp !== '127.0.0.1' && $candidateIp !== '::1') {
                        $realIp = $candidateIp;
                    }
                }

                // If X-Real-IP not found, check X-Forwarded-For
                if ($realIp === $conn->remoteAddress || $realIp === '127.0.0.1') {
                    $xForwardedForValue = null;
                    
                    // Try getHeaderLine() first
                    if (method_exists($request, 'getHeaderLine')) {
                        try {
                            $xForwardedForValue = $request->getHeaderLine('X-Forwarded-For');
                            if (!empty($xForwardedForValue)) {
                                $xForwardedForValue = trim($xForwardedForValue);
                            }
                        } catch (\Throwable $e) {
                            // Ignore
                        }
                    }
                    
                    // If getHeaderLine didn't work, try getHeader()
                    if (empty($xForwardedForValue)) {
                        try {
                            $xForwardedFor = $request->getHeader('X-Forwarded-For');
                            $xForwardedForValue = $extractHeaderValue($xForwardedFor);
                        } catch (\Throwable $e) {
                            // Ignore
                        }
                    }
                    
                    // Process X-Forwarded-For value
                    if (!empty($xForwardedForValue)) {
                        $ips = array_map('trim', explode(',', $xForwardedForValue));
                        // Берем последний IP в цепочке (реальный IP клиента)
                        $lastIp = end($ips);
                        if (!empty($lastIp) && $lastIp !== '127.0.0.1' && $lastIp !== '::1') {
                            $realIp = $lastIp;
                        } else {
                            // Если последний IP loopback, пробуем первый
                            $firstIp = reset($ips);
                            if (!empty($firstIp) && $firstIp !== '127.0.0.1' && $firstIp !== '::1') {
                                $realIp = $firstIp;
                            }
                        }
                    }
                }
                
                // If still localhost, check original headers from DDoS-Guard (X-Forwarded-For-Original, X-Real-IP-Original)
                if ($realIp === $conn->remoteAddress || $realIp === '127.0.0.1') {
                    // Try X-Real-IP-Original (original header from DDoS-Guard)
                    $xRealIpOriginalValue = null;
                    if (method_exists($request, 'getHeaderLine')) {
                        try {
                            $xRealIpOriginalValue = $request->getHeaderLine('X-Real-IP-Original');
                            if (!empty($xRealIpOriginalValue)) {
                                $xRealIpOriginalValue = trim($xRealIpOriginalValue);
                            }
                        } catch (\Throwable $e) {
                            // Ignore
                        }
                    }
                    if (empty($xRealIpOriginalValue)) {
                        try {
                            $xRealIpOriginal = $request->getHeader('X-Real-IP-Original');
                            $xRealIpOriginalValue = $extractHeaderValue($xRealIpOriginal);
                        } catch (\Throwable $e) {
                            // Ignore
                        }
                    }
                    if (!empty($xRealIpOriginalValue) && $xRealIpOriginalValue !== '127.0.0.1' && $xRealIpOriginalValue !== '::1') {
                        $realIp = $xRealIpOriginalValue;
                    }
                    
                    // Try X-Forwarded-For-Original (original header from DDoS-Guard)
                    if ($realIp === $conn->remoteAddress || $realIp === '127.0.0.1') {
                        $xForwardedForOriginalValue = null;
                        if (method_exists($request, 'getHeaderLine')) {
                            try {
                                $xForwardedForOriginalValue = $request->getHeaderLine('X-Forwarded-For-Original');
                                if (!empty($xForwardedForOriginalValue)) {
                                    $xForwardedForOriginalValue = trim($xForwardedForOriginalValue);
                                }
                            } catch (\Throwable $e) {
                                // Ignore
                            }
                        }
                        if (empty($xForwardedForOriginalValue)) {
                            try {
                                $xForwardedForOriginal = $request->getHeader('X-Forwarded-For-Original');
                                $xForwardedForOriginalValue = $extractHeaderValue($xForwardedForOriginal);
                            } catch (\Throwable $e) {
                                // Ignore
                            }
                        }
                        if (!empty($xForwardedForOriginalValue)) {
                            $ips = array_map('trim', explode(',', $xForwardedForOriginalValue));
                            // Берем первый IP (реальный IP клиента от DDoS-Guard)
                            $firstIp = reset($ips);
                            if (!empty($firstIp) && $firstIp !== '127.0.0.1' && $firstIp !== '::1') {
                                $realIp = $firstIp;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Log only critical errors
                if ($realIp === '127.0.0.1' || $realIp === '::1') {
                    $this->log("onOpen: Error processing headers: " . $e->getMessage());
                }
            }
        }

        // Fallback: try to get IP from $_SERVER (if available in this context)
        if (($realIp === '127.0.0.1' || $realIp === '::1') && isset($_SERVER)) {
            try {
                // Check original headers from DDoS-Guard first (X-Real-IP-Original, X-Forwarded-For-Original)
                if (!empty($_SERVER['HTTP_X_REAL_IP_ORIGINAL'])) {
                    $candidateIp = trim($_SERVER['HTTP_X_REAL_IP_ORIGINAL']);
                    if ($candidateIp !== '127.0.0.1' && $candidateIp !== '::1') {
                        $realIp = $candidateIp;
                    }
                }
                
                // Check X-Forwarded-For-Original from DDoS-Guard
                if (($realIp === '127.0.0.1' || $realIp === '::1') && !empty($_SERVER['HTTP_X_FORWARDED_FOR_ORIGINAL'])) {
                    $forwardedFor = trim($_SERVER['HTTP_X_FORWARDED_FOR_ORIGINAL']);
                    $ips = array_map('trim', explode(',', $forwardedFor));
                    // Берем первый IP (реальный IP клиента от DDoS-Guard)
                    $firstIp = reset($ips);
                    if (!empty($firstIp) && $firstIp !== '127.0.0.1' && $firstIp !== '::1') {
                        $realIp = $firstIp;
                    }
                }
                
                // Check X-Real-IP from $_SERVER (processed by Nginx)
                if (($realIp === '127.0.0.1' || $realIp === '::1') && !empty($_SERVER['HTTP_X_REAL_IP'])) {
                    $candidateIp = trim($_SERVER['HTTP_X_REAL_IP']);
                    if ($candidateIp !== '127.0.0.1' && $candidateIp !== '::1') {
                        $realIp = $candidateIp;
                    }
                }

                // If still localhost, check X-Forwarded-For from $_SERVER (processed by Nginx)
                if (($realIp === '127.0.0.1' || $realIp === '::1') && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $forwardedFor = trim($_SERVER['HTTP_X_FORWARDED_FOR']);
                    $ips = array_map('trim', explode(',', $forwardedFor));
                    // Берем последний IP в цепочке (реальный IP клиента)
                    $lastIp = end($ips);
                    if (!empty($lastIp) && $lastIp !== '127.0.0.1' && $lastIp !== '::1') {
                        $realIp = $lastIp;
                    } else {
                        // Если последний IP loopback, пробуем первый
                        $firstIp = reset($ips);
                        if (!empty($firstIp) && $firstIp !== '127.0.0.1' && $firstIp !== '::1') {
                            $realIp = $firstIp;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore $_SERVER errors
            }
        }

        // Save real IP to connection property for later use
        $conn->realIp = $realIp;

        // If IP is still localhost, log warning
        if ($realIp === '127.0.0.1' || $realIp === '::1') {
        }

        // Call parent method
        parent::onOpen($conn);
    }

    /**
     * Получить реальный IP клиента из HTTP заголовков (для случая, когда сервер за прокси)
     * @param ConnectionInterface $client
     * @param WSClientEvent|null $event Событие подключения (может содержать доступ к HTTP запросу)
     * @return string IP адрес клиента
     */
    private function getClientRealIp(ConnectionInterface $client, WSClientEvent $event = null)
    {
        $realIp = $client->remoteAddress;

        // Пытаемся получить IP из HTTP заголовков (если доступны)
        try {
            $httpRequest = null;

            // Пытаемся получить httpRequest из события (если передано)
            if ($event) {
                try {
                    $eventReflection = new \ReflectionObject($event);
                    if ($eventReflection->hasProperty('httpRequest')) {
                        $eventProp = $eventReflection->getProperty('httpRequest');
                        $eventProp->setAccessible(true);
                        $httpRequest = $eventProp->getValue($event);
                    }
                } catch (\Throwable $e) {
                    // Игнорируем
                }
            }

            // Пытаемся получить напрямую из клиента через рефлексию
            if (!$httpRequest) {
                try {
                    $reflection = new \ReflectionObject($client);

                    // Пробуем разные возможные имена свойств
                    $possibleProperties = ['httpRequest', '_httpRequest', 'request', '_request'];
                    foreach ($possibleProperties as $propName) {
                        if ($reflection->hasProperty($propName)) {
                            $prop = $reflection->getProperty($propName);
                            $prop->setAccessible(true);
                            $value = $prop->getValue($client);
                            if ($value && (method_exists($value, 'getHeaders') || method_exists($value, 'getHeader'))) {
                                $httpRequest = $value;
                                break;
                            }
                        }
                    }

                    // Если не нашли напрямую, ищем во всех свойствах
                    if (!$httpRequest) {
                        $properties = $reflection->getProperties();
                        foreach ($properties as $prop) {
                            $prop->setAccessible(true);
                            $value = $prop->getValue($client);
                            if ($value && is_object($value) && (method_exists($value, 'getHeaders') || method_exists($value, 'getHeader'))) {
                                $httpRequest = $value;
                                break;
                            }
                        }
                    }
                } catch (\Throwable $reflectionEx) {
                    // Игнорируем ошибки рефлексии
                }
            }

            if ($httpRequest) {
                $headers = [];

                // Пробуем разные методы получения заголовков
                if (method_exists($httpRequest, 'getHeaders')) {
                    $headers = $httpRequest->getHeaders();
                } elseif (method_exists($httpRequest, 'getHeader')) {
                    // Если есть метод getHeader, получаем заголовки по одному
                    $xRealIp = $httpRequest->getHeader('X-Real-IP');
                    $xForwardedFor = $httpRequest->getHeader('X-Forwarded-For');
                    if ($xRealIp) {
                        $headers['X-Real-IP'] = is_array($xRealIp) ? $xRealIp : [$xRealIp];
                    }
                    if ($xForwardedFor) {
                        $headers['X-Forwarded-For'] = is_array($xForwardedFor) ? $xForwardedFor : [$xForwardedFor];
                    }
                }

                // Проверяем X-Real-IP (приоритетный заголовок от Nginx)
                if (isset($headers['X-Real-IP'])) {
                    $xRealIp = is_array($headers['X-Real-IP']) ? $headers['X-Real-IP'][0] : $headers['X-Real-IP'];
                    if (!empty($xRealIp) && $xRealIp !== '127.0.0.1') {
                        $realIp = trim($xRealIp);
                    }
                }
                // Если X-Real-IP нет, проверяем X-Forwarded-For
                elseif (isset($headers['X-Forwarded-For'])) {
                    $xForwardedFor = is_array($headers['X-Forwarded-For']) ? $headers['X-Forwarded-For'][0] : $headers['X-Forwarded-For'];
                    if (!empty($xForwardedFor)) {
                        // X-Forwarded-For может содержать несколько IP через запятую
                        $forwardedFor = trim($xForwardedFor);
                        $ips = explode(',', $forwardedFor);
                        if (!empty($ips[0])) {
                            $candidateIp = trim($ips[0]);
                            if ($candidateIp !== '127.0.0.1') {
                                $realIp = $candidateIp;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Если не удалось получить заголовки, используем remoteAddress
            // Логируем только если это не 127.0.0.1 (чтобы не засорять логи)
            if ($realIp === '127.0.0.1') {
            }
        }

        return $realIp;
    }

    /**
     * Безопасная отправка сообщения клиенту с обработкой ошибок
     * @param ConnectionInterface $client
     * @param mixed $data Данные для отправки (будут закодированы в JSON)
     * @return bool Успешно ли отправлено
     */
    private function safeSend(ConnectionInterface $client, $data)
    {
        try {
            $message = is_string($data) ? $data : json_encode($data);
            $client->send($message);

            // Сбрасываем счетчик ошибок при успешной отправке
            if (isset($client->sendErrors) && $client->sendErrors > 0) {
                $client->sendErrors = 0;
            }

            return true;
        } catch (\Throwable $e) {
            // Увеличиваем счетчик ошибок
            if (!isset($client->sendErrors)) {
                $client->sendErrors = 0;
            }
            $client->sendErrors++;

            $userId = !empty($client->user) ? $client->user->id : 'anonymous';
            $this->log("Error sending message to client {$userId}: " . $e->getMessage() . " (errors: {$client->sendErrors})");

            // Закрываем соединение только после нескольких последовательных ошибок
            if ($client->sendErrors >= 5) {
                $this->log("Closing client {$userId} after {$client->sendErrors} consecutive send errors");
                $client->disconnectReason = 'send_failed';
                try {
                    $client->close(1011, 'send failed');
                } catch (\Throwable $e2) {
                    // Игнорируем ошибки при закрытии
                }
            }

            return false;
        }
    }

    /**
     * Обработка отложенных сообщений из кеша
     */
    private function processQueuedMessage($client, $data)
    {
        $this->safeSend($client, $data);
    }


    /**
     * Получить клиентов по user_id
     * @param int $userId
     * @return array
     */
    public function getClientsByUserId($userId)
    {
        return $this->clientsByUserId[$userId] ?? [];
    }

    /**
     * Получить клиентов по chat
     * @param string $chat
     * @return array
     */
    private function getClientsByChat($chat)
    {
        return $this->clientsByChat[$chat] ?? [];
    }

    /**
     * Добавить клиента в индекс по user_id
     */
    private function indexClientByUserId(ConnectionInterface $client)
    {
        if (!empty($client->user)) {
            $userId = $client->user->id;
            if (!isset($this->clientsByUserId[$userId])) {
                $this->clientsByUserId[$userId] = [];
            }
            if (!in_array($client, $this->clientsByUserId[$userId], true)) {
                $this->clientsByUserId[$userId][] = $client;
            }
        }
    }

    /**
     * Добавить клиента в индекс по chat
     */
    private function indexClientByChat(ConnectionInterface $client)
    {
        if (!empty($client->chat)) {
            $chat = $client->chat;
            if (!isset($this->clientsByChat[$chat])) {
                $this->clientsByChat[$chat] = [];
            }
            if (!in_array($client, $this->clientsByChat[$chat], true)) {
                $this->clientsByChat[$chat][] = $client;
            }
        }
    }

    public function init()
    {
        parent::init();

        // Устанавливаем singleton instance
        self::$instance = $this;

            $this->on(self::EVENT_CLIENT_CONNECTED, function(WSClientEvent $e) {
            $this->clientsArrayCache = null; // инвалидация кеша для таймера

            // Используем реальный IP, который был сохранен в onOpen
            // Если realIp не установлен, пытаемся получить его через getClientRealIp
            if (isset($e->client->realIp)) {
                $ip = $e->client->realIp;
            } else {
                // Fallback: пытаемся получить IP через заголовки (на случай, если onOpen не сработал)
                $originalRemoteAddress = $e->client->remoteAddress;
                $ip = $this->getClientRealIp($e->client, $e);

                // Если получили localhost IP, это означает, что заголовки недоступны
                // В этом случае используем уникальный идентификатор соединения для rate limiting
                $isLocalhost = ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost' || strpos($ip, '127.') === 0);
                if ($isLocalhost) {
                    // Генерируем уникальный идентификатор на основе resourceId соединения
                    try {
                        $resourceId = method_exists($e->client, 'resourceId') ? $e->client->resourceId : spl_object_hash($e->client);
                        $ip = 'proxy_' . $resourceId;
                    } catch (\Throwable $ex) {
                        // Если не удалось получить resourceId, используем хеш объекта
                        $ip = 'proxy_' . md5(spl_object_hash($e->client) . microtime(true));
                    }
                }

                // Сохраняем IP для дальнейшего использования
                $e->client->realIp = $ip;
            }

            // Сохраняем реальный IP в свойство клиента для дальнейшего использования
            $e->client->realIp = $ip;
            $now = time();

            // Rate limiting: проверяем количество подключений с одного IP
            if (!isset($this->connectionsByIp[$ip])) {
                $this->connectionsByIp[$ip] = [];
                $this->lastConnectionTimeByIp[$ip] = [];
            }

            // Подсчитываем активные подключения с этого IP
            $activeConnectionsFromIp = 0;
            foreach ($this->clients as $client) {
                $clientIp = isset($client->realIp) ? $client->realIp : $client->remoteAddress;
                if ($clientIp === $ip) {
                    $activeConnectionsFromIp++;
                }
            }

            // Проверяем лимит одновременных подключений
            // Используем > вместо >=, так как текущее соединение уже учтено в подсчёте
            if ($activeConnectionsFromIp > $this->maxConnectionsPerIp) {
                $this->log("Rejecting connection from {$ip}: too many connections ({$activeConnectionsFromIp})");
                try {
                    $e->client->close(1008, 'Too many connections from this IP');
                } catch (\Throwable $ex) {
                    // Игнорируем ошибки при закрытии
                }
                return;
            }

            // Проверяем rate limiting (подключений в секунду)
            $recentConnections = 0;
            foreach ($this->lastConnectionTimeByIp[$ip] as $connectionTime) {
                if (($now - $connectionTime) < 1) {
                    $recentConnections++;
                }
            }

            // Используем > вместо >=, чтобы разрешить точно maxConnectionsPerSecond подключений в секунду
            if ($recentConnections > $this->maxConnectionsPerSecond) {
                $this->log("Rejecting connection from {$ip}: rate limit exceeded ({$recentConnections} connections in last second)");
                try {
                    $e->client->close(1008, 'Connection rate limit exceeded');
                } catch (\Throwable $ex) {
                    // Игнорируем ошибки при закрытии
                }
                return;
            }

            // Регистрируем подключение
            $this->connectionsByIp[$ip][] = $now;
            $this->lastConnectionTimeByIp[$ip][] = $now;

            // Очищаем старые записи (старше 60 секунд)
            $this->connectionsByIp[$ip] = array_filter($this->connectionsByIp[$ip], function($time) use ($now) {
                return ($now - $time) < 60;
            });
            $this->lastConnectionTimeByIp[$ip] = array_filter($this->lastConnectionTimeByIp[$ip], function($time) use ($now) {
                return ($now - $time) < 60;
            });

            $e->client->user = null;
            $e->client->chat = null;
            $e->client->launcher = false;

            // heartbeat state
            $e->client->lastPong = time();
            $e->client->connectedAt = time(); // Время подключения для проверки таймаута авторизации
            $e->client->alive = true;
            $e->client->sendErrors = 0; // Счетчик ошибок отправки
            $e->client->disconnectReason = 'client_close'; // Причина отключения по умолчанию
        });
        $this->on(self::EVENT_CLIENT_DISCONNECTED, function(WSClientEvent $e) {
            $this->clientsArrayCache = null; // инвалидация кеша для таймера

            $userId = !empty($e->client->user) ? $e->client->user->id : 'anonymous';
            $reason = isset($e->client->disconnectReason) ? $e->client->disconnectReason : 'unknown';
            $idleTime = isset($e->client->lastPong) ? (time() - $e->client->lastPong) : 'N/A';
            $ip = isset($e->client->realIp) ? $e->client->realIp : $e->client->remoteAddress;

            // Очищаем счетчики подключений для этого IP (если нет активных подключений)
            $activeFromIp = 0;
            foreach ($this->clients as $client) {
                $clientIp = isset($client->realIp) ? $client->realIp : $client->remoteAddress;
                if ($clientIp === $ip) {
                    $activeFromIp++;
                }
            }
            if ($activeFromIp === 0 && isset($this->connectionsByIp[$ip])) {
                // Нет активных подключений с этого IP - очищаем счетчики
                unset($this->connectionsByIp[$ip]);
                unset($this->lastConnectionTimeByIp[$ip]);
            }

            // Удаляем из индексов
            if (!empty($e->client->user)) {
                $userId = $e->client->user->id;
                if (isset($this->clientsByUserId[$userId])) {
                    $key = array_search($e->client, $this->clientsByUserId[$userId], true);
                    if ($key !== false) {
                        unset($this->clientsByUserId[$userId][$key]);
                        if (empty($this->clientsByUserId[$userId])) {
                            unset($this->clientsByUserId[$userId]);
                        }
                    }
                }
            }
            if (!empty($e->client->chat)) {
                $chat = $e->client->chat;
                if (isset($this->clientsByChat[$chat])) {
                    $key = array_search($e->client, $this->clientsByChat[$chat], true);
                    if ($key !== false) {
                        unset($this->clientsByChat[$chat][$key]);
                        if (empty($this->clientsByChat[$chat])) {
                            unset($this->clientsByChat[$chat]);
                        }
                    }
                }
            }

            // Уведомляем других пользователей в том же чате
            if (!empty($e->client->chat) && !empty($e->client->user)) {
                $chatClients = $this->getClientsByChat($e->client->chat);
                foreach ($chatClients as $chatClient) {
                    if ($chatClient !== $e->client && !empty($chatClient->user)) {
                        try {
                            $chatClient->send(json_encode(['type' => 'chatBlur']));
                        } catch (\Exception $ex) {
                            $this->log("Error sending chatBlur: " . $ex->getMessage());
                        }
                    }
                }
            }
        });

        // После старта сокета есть loop
        $this->on(self::EVENT_WEBSOCKET_OPEN, function () {
            /** @var \Ratchet\Server\IoServer $io */
            $io = $this->server;
            $loop = $io->loop;

            // Одноразовый вывод через 60 сек после старта — сколько соединений // NEW
            $loop->addTimer(60, function () {
            });

            // Ping чанками каждые 5 сек, чтобы не блокировать цикл (раньше раз в 30 сек обход всех клиентов)
            $loop->addPeriodicTimer(5, function () {
                $this->statsPingTicks++;
                if ($this->clientsArrayCache === null) {
                    $this->clientsArrayCache = [];
                    foreach ($this->clients as $c) {
                        $this->clientsArrayCache[] = $c;
                    }
                }
                $arr = $this->clientsArrayCache;
                $total = count($arr);
                if ($total === 0) return;
                $now = time();
                $chunkSize = self::PING_TIMER_CHUNK;
                $start = $this->pingTimerOffset % $total;
                $this->pingTimerOffset = ($start + $chunkSize) % $total;
                $chunk = array_slice($arr, $start, $chunkSize);
                foreach ($chunk as $client) {
                    if (!isset($client->sendErrors)) $client->sendErrors = 0;
                    if (empty($client->user) && isset($client->connectedAt)) {
                        if (($now - $client->connectedAt) >= $this->authTimeoutSeconds) {
                            $client->disconnectReason = 'auth_timeout';
                            try { $client->close(1008, 'authentication timeout'); } catch (\Throwable $e) {}
                            continue;
                        }
                    }
                    $idle = $now - (isset($client->lastPong) ? $client->lastPong : 0);
                    if ($idle >= $this->idleCloseSeconds) {
                        $client->disconnectReason = 'heartbeat_timeout';
                        try { $client->close(1000, 'heartbeat timeout'); } catch (\Throwable $e) {}
                        continue;
                    }
                    try {
                        $client->send(json_encode(['type' => 'ping', 'ts' => $now]));
                        if (isset($client->sendErrors) && $client->sendErrors > 0) {
                            $client->sendErrors = max(0, $client->sendErrors - 1);
                        }
                    } catch (\Throwable $e) {
                        $client->sendErrors = isset($client->sendErrors) ? $client->sendErrors + 1 : 1;
                        if ($client->sendErrors >= 3) {
                            $client->disconnectReason = 'send_failed';
                            try { $client->close(1011, 'send failed'); } catch (\Throwable $e2) {}
                        }
                    }
                }
            });

            // Отчёт в лог раз в минуту (клиенты, счётчики событий, память) — без нагрузки
            $loop->addPeriodicTimer(60, function () {
                $clients = $this->clientsArrayCache !== null ? count($this->clientsArrayCache) : iterator_count($this->clients);
                $chats = count($this->clientsByChat);
                $clientsInChat = 0;
                foreach ($this->clientsByChat as $list) {
                    $clientsInChat += count($list);
                }
                $mem = round(memory_get_usage(true) / 1024 / 1024, 1);
                $this->log(sprintf(
                    'report | clients=%d chats=%d inChat=%d chatSent=%d subscription=%d supportTicks=%d pingTicks=%d memory=%sMb',
                    $clients,
                    $chats,
                    $clientsInChat,
                    $this->statsChatSent,
                    $this->statsSubscription,
                    $this->statsSupportTicks,
                    $this->statsPingTicks,
                    $mem
                ));
                $this->statsChatSent = 0;
                $this->statsSubscription = 0;
                $this->statsSupportTicks = 0;
                $this->statsPingTicks = 0;
            });

            // Обработка support событий из кеша каждые 0.5 сек (чаще = быстрее реакция на входящие сообщения)
            $loop->addPeriodicTimer(0.5, function () {
                $this->statsSupportTicks++;
                try {
                    if ($this->clientsArrayCache === null) {
                        $this->clientsArrayCache = [];
                        foreach ($this->clients as $c) {
                            $this->clientsArrayCache[] = $c;
                        }
                    }
                    $clientsArr = $this->clientsArrayCache;
                    $total = count($clientsArr);
                    if ($total === 0) {
                        return;
                    }
                    $chunkSize = self::SUPPORT_TIMER_CHUNK;
                    $start = $this->supportTimerOffset % $total;
                    $this->supportTimerOffset = ($start + $chunkSize) % $total;
                    $chunk = array_slice($clientsArr, $start, $chunkSize);

                    foreach ($chunk as $client) {
                        try {
                            if (!empty($client->chat)) {
                                $statusKey = 'ws_support_status_' . $client->chat;
                                $statusData = Yii::$app->cache->get($statusKey);
                                if ($statusData && (time() - $statusData['timestamp']) < 5) {
                                    if (!isset($statusData['sent'])) {
                                        $chatClients = $this->getClientsByChat($client->chat);
                                        foreach ($chatClients as $chatClient) {
                                            $this->processQueuedMessage($chatClient, $statusData);
                                        }
                                        $statusData['sent'] = true;
                                        Yii::$app->cache->set($statusKey, $statusData, 5);
                                    }
                                }

                                $chatKey = 'ws_chat_update_' . $client->chat;
                                $chatData = Yii::$app->cache->get($chatKey);
                                if ($chatData && isset($chatData['timestamp']) && (time() - $chatData['timestamp']) < 5) {
                                    if (!isset($chatData['sent'])) {
                                        $chatClients = $this->getClientsByChat($client->chat);
                                        foreach ($chatClients as $chatClient) {
                                            $response = [
                                                'type' => 'chat',
                                                'messageId' => $chatData['messageId'] ?? null,
                                            ];
                                            if (!empty($chatData['tempId'])) {
                                                $response['tempId'] = $chatData['tempId'];
                                            }
                                            $this->processQueuedMessage($chatClient, $response);
                                        }
                                        $chatData['sent'] = true;
                                        Yii::$app->cache->set($chatKey, $chatData, 5);
                                    }
                                }
                            }

                            // Только ticket — быстро; balance/drops/launcher в отдельном таймере (2 сек), чтобы не блокировать чат
                            if (!empty($client->user)) {
                                $ticketKey = 'ws_ticket_update_' . $client->user->id;
                                $ticketData = Yii::$app->cache->get($ticketKey);
                                if ($ticketData && (time() - $ticketData['timestamp']) < 5) {
                                    if (!isset($ticketData['sent'])) {
                                        $userClients = $this->getClientsByUserId($client->user->id);
                                        foreach ($userClients as $userClient) {
                                            $this->processQueuedMessage($userClient, $ticketData);
                                        }
                                        $ticketData['sent'] = true;
                                        Yii::$app->cache->set($ticketKey, $ticketData, 5);
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            $this->log("Error processing support event: " . $e->getMessage());
                        }
                    }
                } catch (\Throwable $e) {
                    $this->log("Error processing support events: " . $e->getMessage());
                }
            });

            // Тяжёлые balance/drops/launcher раз в 2 сек, маленький чанк — чтобы не блокировать чат надолго
            $loop->addPeriodicTimer(2, function () {
                try {
                    if ($this->clientsArrayCache === null) {
                        return;
                    }
                    $arr = $this->clientsArrayCache;
                    $total = count($arr);
                    if ($total === 0) return;
                    $chunkSize = 10;
                    $start = $this->balanceTimerOffset % $total;
                    $this->balanceTimerOffset = ($start + $chunkSize) % $total;
                    $chunk = array_slice($arr, $start, $chunkSize);
                    $buyDropCount = 0;
                    foreach ($chunk as $client) {
                        try {
                            if (empty($client->user)) continue;
                            $balanceKey = 'ws_balance_update_' . $client->user->id;
                            $balanceData = Yii::$app->cache->get($balanceKey);
                            if ($balanceData && (time() - $balanceData['timestamp']) < 30) {
                                if (!isset($balanceData['sent'])) {
                                    $userClients = $this->getClientsByUserId($client->user->id);
                                    foreach ($userClients as $userClient) {
                                        $this->processQueuedMessage($userClient, $balanceData);
                                    }
                                    $balanceData['sent'] = true;
                                    Yii::$app->cache->set($balanceKey, $balanceData, 5);
                                }
                            }
                            $listKey = 'ws_drops_list_' . $client->user->id;
                            $dropsList = Yii::$app->cache->get($listKey);
                            if ($dropsList && is_array($dropsList) && count($dropsList) > 0) {
                                $userClients = $this->getClientsByUserId($client->user->id);
                                foreach ($dropsList as $dropId) {
                                    if ($buyDropCount >= self::SUPPORT_TIMER_BUY_DROP_PER_TICK) break;
                                    $buyKey = 'ws_buy_drop_' . $client->user->id . '_' . $dropId;
                                    $buyData = Yii::$app->cache->get($buyKey);
                                    if ($buyData && isset($buyData['timestamp']) && (time() - $buyData['timestamp']) < 30) {
                                        if (!isset($buyData['sent'])) {
                                            $this->commandBuyDrop($client, json_encode($buyData));
                                            $buyDropCount++;
                                            $buyData['sent'] = true;
                                            Yii::$app->cache->set($buyKey, $buyData, 5);
                                        }
                                    }
                                    $activatedKey = 'ws_activated_drop_' . $client->user->id . '_' . $dropId;
                                    $activatedData = Yii::$app->cache->get($activatedKey);
                                    if ($activatedData && isset($activatedData['timestamp']) && (time() - $activatedData['timestamp']) < 30) {
                                        if (!isset($activatedData['sent'])) {
                                            foreach ($userClients as $userClient) {
                                                $this->processQueuedMessage($userClient, $activatedData);
                                            }
                                            $activatedData['sent'] = true;
                                            Yii::$app->cache->set($activatedKey, $activatedData, 5);
                                        }
                                    }
                                    $returnKey = 'ws_return_drop_' . $client->user->id . '_' . $dropId;
                                    $returnData = Yii::$app->cache->get($returnKey);
                                    if ($returnData && isset($returnData['timestamp']) && (time() - $returnData['timestamp']) < 30) {
                                        if (!isset($returnData['sent'])) {
                                            foreach ($userClients as $userClient) {
                                                $this->processQueuedMessage($userClient, $returnData);
                                            }
                                            $returnData['sent'] = true;
                                            Yii::$app->cache->set($returnKey, $returnData, 5);
                                        }
                                    }
                                }
                            }
                            if (!empty($client->launcher)) {
                                for ($i = 0; $i < 10; $i++) {
                                    $launcherKey = 'ws_launcher_update_' . (time() - $i);
                                    $launcherData = Yii::$app->cache->get($launcherKey);
                                    if ($launcherData && (time() - $launcherData['timestamp']) < 5) {
                                        $this->processQueuedMessage($client, $launcherData);
                                        break;
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            $this->log("Error processing balance/drops event: " . $e->getMessage());
                        }
                    }
                } catch (\Throwable $e) {
                    $this->log("Error in balance/drops timer: " . $e->getMessage());
                }
            });
        });
    }

    protected function getCommand(ConnectionInterface $from, $msg)
    {
        // Любое входящее — клиент «жив»
        $from->lastPong = time(); // CHANGED (оставляем)
        $from->alive = true;

        // Сбрасываем счетчик ошибок при получении любого сообщения
        if (isset($from->sendErrors) && $from->sendErrors > 0) {
            $from->sendErrors = 0;
        }

        $request = json_decode($msg, true);

        // Логируем ошибки парсинга JSON
        if ($request === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->log("JSON parse error: " . json_last_error_msg() . " | Message: " . substr($msg, 0, 200));
            $request = [];
        }

        // Принимаем pong и как action, и как type // NEW
        if (
            (isset($request['action']) && $request['action'] === 'pong') ||
            (isset($request['type']) && $request['type'] === 'pong')
        ) {
            // commandPong будет вызван через return 'pong'
            return 'pong';
        }

        $action = !empty($request['action']) ? $request['action'] : parent::getCommand($from, $msg);

        // Логируем команды (кроме pong, чтобы не засорять логи)
        if ($action !== 'pong' && $action !== 'Pong') {
            $userId = !empty($from->user) ? $from->user->id : 'anonymous';
        }

        return $action;
    }

    public function commandSubscription(ConnectionInterface $client, $msg)
    {
        try {
            $request = json_decode($msg, true);
            $result = ['message' => ''];

            if (!empty($client->user) && !empty($request['chat'])) {
                // Нормализуем chat ID (приводим к строке для единообразия)
                $chatId = (string)$request['chat'];

                // Кешируем поиск тикета на 30 секунд
                $cacheKey = 'ws_ticket_' . $chatId;
                $ticket = Yii::$app->cache->getOrSet($cacheKey, function() use ($chatId) {
                    return Support::findByNumber($chatId);
                }, 30);

                // Если тикет существует - проверяем права доступа
                if ($ticket) {
                    if ($client->user->canRoles([Role::ROLE_ADMIN]) ||
                        $client->user->canRoles([Role::ROLE_MODERATOR]) ||
                        $client->user->canRoles([Role::ROLE_SUPPORT]) ||
                        $ticket->user_id == $client->user->id
                    ) {
                        $client->chat = $chatId;
                        $this->indexClientByChat($client);
                        $this->statsSubscription++;
                    }
                } else {
                    $client->chat = $chatId;
                    $this->indexClientByChat($client);
                    $this->statsSubscription++;
                }
            }

            // Не шлём пустой ответ — клиенту не нужен ack для подписки
        } catch (\Exception $ex) {
            $this->log("Subscription error: " . $ex->getMessage());
        }
    }

    public function commandGetDrop(ConnectionInterface $client, $msg)
    {
      try {
          $request = json_decode($msg, true);
          $result = ['message' => ''];

          if (!empty($client->user) && !empty($request['id'])) {

              $model = UserDrop::findOne($request['id']);
              if ($client->user->id != $model->user->id) {
                  $this->safeSend($client, [
                                                'type' => 'store.take',
                                                'code' => 500,
                                                'message' => Yii::t('common', "Товар вам не принадлежит!", [], $client->user->current_language),
                                                'id' => $model->id,
                                            ]);
                  return;
              }

              // Атомарная блокировка предмета на время обработки (используем общий ключ для getDrop и returnDrop)
              $lockKey = 'userDrop_lock_' . $model->id;
              if (Yii::$app->cache->get($lockKey)) {
                  $this->safeSend($client, [
                                                'type' => 'store.take',
                                                'code' => 500,
                                                'message' => Yii::t('common', "Предмет уже обрабатывается, подождите немного!", [], $client->user->current_language),
                                                'id' => $model->id,
                                            ]);
                  return;
              }
              Yii::$app->cache->set($lockKey, true, 10); // Блокируем на 10 секунд

              // Перезагружаем модель после блокировки для проверки актуального статуса
              $model->refresh();

              // Защита от повторного вывода: проверяем статус предмета после блокировки
              if ($model->status != UserDrop::STATUS_ACTIVE) {
                  Yii::$app->cache->delete($lockKey); // Снимаем блокировку
                  $statusText = UserDrop::getStatusList()[$model->status] ?? 'Недоступен';
                  $this->safeSend($client, [
                                                'type' => 'store.take',
                                                'code' => 500,
                                                'message' => Yii::t('common', "Товар уже был выведен или недоступен! Статус: {status}", ['status' => $statusText], $client->user->current_language),
                                                'id' => $model->id,
                                            ]);
                  return;
              }

              if (empty($model->user->server)) {
                  Yii::$app->cache->delete($lockKey); // Снимаем блокировку
                  $this->safeSend($client, [
                                                'type' => 'store.take',
                                                'code' => 500,
                                                'message' => Yii::t('common', "Мы не нашли вас на сервере!", [], $client->user->current_language),
                                                'id' => $model->id,
                                            ]);
                  return;
              }
              if (DropBlocked::getBlocked($model->drop_id, $model->user->server->id, true)) {
                  Yii::$app->cache->delete($lockKey); // Снимаем блокировку
                  $this->safeSend($client, [
                                                'type' => 'store.take',
                                                'code' => 500,
                                                'message' => Yii::t('common', "Товар в вайп-блоке!", [], $client->user->current_language),
                                                'id' => $model->id,
                                            ]);
                  return;
              }

              $cacheKey = 'commandGetDrop_kd_' . $model->user->id;
              $count = Yii::$app->cache->get($cacheKey) ?? 0;
              if ($count > 5) {
                  Yii::$app->cache->delete($lockKey); // Снимаем блокировку
                  $this->safeSend($client, [
                                                'type' => 'store.take',
                                                'code' => 500,
                                                'message' => Yii::t('common', "Нельзя выполнять действия слишком часто! Подождите 30 секунд.", [], $client->user->current_language),
                                                'id' => $model->id,
                                            ]);
                  return;
              }
              Yii::$app->cache->set($cacheKey, $count + 1, 30);

              if ($client->user->canRoles([Role::ROLE_ADMIN]) || $client->user->canRoles([Role::ROLE_MODERATOR]) || $client->user->canRoles([Role::ROLE_SUPPORT]) || $model->user_id == $client->user->id) {
                  // Получаем drop для проверки is_blocked_building
                  $drop = $model->dropOne;
                  if (empty($drop)) {
                      $drop = \common\models\box\Drop::findOne($model->drop_id);
                  }
                  if (empty($drop)) {
                      Yii::$app->cache->delete($lockKey); // Снимаем блокировку
                      $this->safeSend($client, [
                          'type' => 'store.take',
                          'code' => 500,
                          'message' => Yii::t('common', "Предмет не найден!", [], $client->user->current_language),
                          'id' => $model->id,
                      ]);
                      return;
                  }

                  // Меняем статус на WAIT только после всех проверок и перед отправкой на сервер
                  $model->status = UserDrop::STATUS_WAIT;
                  $model->save(false);
                  $isBlockedBuilding = $drop->is_blocked_building ? 'true' : 'false';
                  $command = "store.take {$model->user->steam_id} {$model->id} {$isBlockedBuilding}";
                  $response = (Yii::$app->curl)
                      ->setHeaders(['Content-Type' => 'application/json'])
                      ->setRawPostData(json_encode(['server' => $model->user->server->tag, 'command' => $command]))
                      ->post(Yii::$app->settings->get('site_rconUrl') . '/send');
                  $rconTask = new RconTasks();
                  $rconTask->status = RconTasks::STATUS_DONE;
                  $rconTask->command = $command;
                  $rconTask->result = $response;
                  $rconTask->server_tag = $model->user->server->tag;
                  $rconTask->created_at = date('Y-m-d H:i:s');
                  $rconTask->save();

                  try {
                      if (empty($response)) {
                          Yii::$app->cache->delete($lockKey); // Снимаем блокировку при ошибке
                          $model->status = UserDrop::STATUS_ACTIVE;
                          $model->save(false);
                          $this->safeSend($client, [
                                                        'type' => 'store.take',
                                                        'code' => 500,
                                                        'message' => Yii::t('common', "Произошла ошибка, попробуйте позже!", [], $client->user->current_language),
                                                        'id' => $model->id,
                                                    ]);
                          return;
                      }
                      $data = json_decode(json_decode($response, 1)['result'], 1);
                      if (!isset($data['success'])) {
                          Yii::$app->cache->delete($lockKey); // Снимаем блокировку при ошибке
                          $model->status = UserDrop::STATUS_ACTIVE;
                          $model->save(false);
                          $this->safeSend($client, [
                                                        'type' => 'store.take',
                                                        'code' => 500,
                                                        'message' => Yii::t('common', "Произошла ошибка, попробуйте позже!", [], $client->user->current_language),
                                                        'id' => $model->id,
                                                    ]);
                          return;
                      }
                      if (!$data['success']) {
                          Yii::$app->cache->delete($lockKey); // Снимаем блокировку при ошибке
                          $model->status = UserDrop::STATUS_ACTIVE;
                          $model->save(false);
                          $this->safeSend($client, [
                                                        'type' => 'store.take',
                                                        'code' => 500,
                                                        'message' => $data['error'],
                                                        'id' => $model->id,
                                                    ]);
                          return;
                      }
                      if ($data['success']) {
                          // Успешная выдача - меняем статус предмета на "Отправлен"
                          Yii::$app->cache->delete($lockKey); // Снимаем блокировку
                          $this->safeSend($client, [
                                                        'type' => 'store.take',
                                                        'code' => 200,
                                                        'message' => Yii::t('common', "Товар успешно получен!", [], $client->user->current_language),
                                                        'id' => $model->id,
                                                    ]);
                          return;
                      }
                  } catch (\Exception $e) {
                      Yii::$app->cache->delete($lockKey); // Снимаем блокировку при исключении
                      $model->status = UserDrop::STATUS_ACTIVE;
                      $model->save(false);
                      Yii::$app->telegramChats->sendMessage($e->getFile() . ":" . $e->getLine() . "; " . $e->getMessage() . "; " . $model->id . "; " . $model->user->steam_id . "; " . $command . "; " . $response);
                      $this->safeSend($client, [
                                                    'type' => 'store.take',
                                                    'code' => 500,
                                                    'message' => Yii::t('common', "Произошла ошибка, попробуйте позже!", [], $client->user->current_language),
                                                    'id' => $model->id,
                                                ]);
                  }

                  return;
              }
          }

          $this->safeSend($client, $result);
      } catch (\Exception $e) {
          Yii::$app->telegramChats->sendMessage('commandGetDrop: ' . $e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
          Yii::error('commandGetDrop: ' . $e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage(), __METHOD__);
          // Пытаемся отправить ошибку клиенту
          try {
              $this->safeSend($client, [
                  'type' => 'store.take',
                  'code' => 500,
                  'message' => Yii::t('common', "Произошла ошибка, попробуйте позже!", [], !empty($client->user) ? $client->user->current_language : 'ru-RU'),
              ]);
          } catch (\Exception $sendEx) {
              // Игнорируем ошибки отправки при общей ошибке
          }
      }
    }

    public function commandSupportStatus(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        if (!empty($request['id'])) {
            // Используем индекс для быстрого поиска клиентов по chat
            $chatClients = $this->getClientsByChat($request['id']);
            $model = new Support();
            $response = json_encode(['type' => 'redirect', 'url' => $model->getUrl()]);

            foreach ($chatClients as $chatClient) {
                try {
                    $chatClient->send($response);
                } catch (\Exception $ex) {
                    $this->log("Error sending support status: " . $ex->getMessage());
                }
            }
        }
    }

    public function commandTicketUpdate(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        if (!empty($request['user_id'])) {
            // Сначала отправляем конкретному пользователю
            $userClients = $this->getClientsByUserId($request['user_id']);
            $response = json_encode(['type' => 'ticketsUpdate']);

            foreach ($userClients as $chatClient) {
                if (!empty($chatClient->chat)) {
                    try {
                        $chatClient->send($response);
                    } catch (\Exception $ex) {
                        $this->log("Error sending ticket update: " . $ex->getMessage());
                    }
                }
            }

            // Затем отправляем админам/модераторам (перебираем всех, но это редкий случай)
            foreach ($this->clients as $chatClient) {
                if (empty($chatClient->chat) || empty($chatClient->user)) {
                    continue;
                }
                /** @var User $user */
                $user = $chatClient->user;
                if ($user->id == $request['user_id']) {
                    continue; // уже отправили выше
                }
                if ($user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT])) {
                    try {
                        $chatClient->send($response);
                    } catch (\Exception $ex) {
                        $this->log("Error sending ticket update to admin: " . $ex->getMessage());
                    }
                }
            }
        }
    }

    public function commandLauncherUpdate(ConnectionInterface $client, $msg)
    {
        foreach ($this->clients as $chatClient) {
            if (empty($chatClient) || !$chatClient->launcher) {
                continue;
            }
            $chatClient->send(json_encode(['type' => 'launcherUpdate']));
        }
    }

    public function commandChatUpdate(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        if (!empty($request['user_id']) && !empty($request['id'])) {
            // Используем индекс для быстрого поиска клиентов по chat
            $chatClients = $this->getClientsByChat($request['id']);
            $response = json_encode(['type' => 'chat', 'messageId' => $request['messageId']]);

            foreach ($chatClients as $chatClient) {
                try {
                    $chatClient->send($response);
                } catch (\Exception $ex) {
                    $this->log("Error sending chat update: " . $ex->getMessage());
                }
            }

            $this->commandTicketUpdate($client, json_encode(['user_id' => $request['user_id']]));
        }
    }

    public function commandBuyDrop(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        $result = ['message' => ''];

        if (!empty($request['id'])) {
            $model = UserDrop::findOne($request['id']);
            if (empty($model->user->server_id)) {
                return;
            }

            // Используем индекс для быстрого поиска клиентов пользователя
            $userClients = $this->getClientsByUserId($model->user->id);

            if ($request['code'] == 200) {
                try {
                    $response = json_encode([
                        'type'    => 'store.buy.items',
                        'code'    => 200,
                        'id'      => $model->id,
                        'product' => Yii::$app->view->renderFile(Yii::getAlias('@frontend/views/store') . '/_product.php', [
                            'drop' => $model->drop[0],
                            'serverId' => $model->user->server_id,
                            'userDrop' => $model,
                        ]),
                    ]);

                    foreach ($userClients as $chatClient) {
                        try {
                            $chatClient->send($response);
                        } catch (\Exception $e) {
                            $this->log("Error sending buy drop to client: " . $e->getMessage());
                        }
                    }
                } catch (\Exception $e) {
                    $this->log("Error rendering product: " . $e->getFile() . ":" . $e->getLine() . " " . $e->getMessage());
                }
            }
        }
        $this->safeSend($client, $result);
    }

    public function commandReturnDrop(ConnectionInterface $client, $msg)
    {
        try {
            $request = json_decode($msg, true);
            $result = ['message' => ''];

            if (!empty($client->user) && !empty($request['id'])) {
                $model = UserDrop::findOne($request['id']);

                if (!$model) {
                    $this->safeSend($client, [
                        'type' => 'store.return.item',
                        'code' => 500,
                        'message' => Yii::t('common', "Товар не найден!", [], $client->user->current_language),
                        'id' => $request['id'],
                    ]);
                    return;
                }

                if ($client->user->id != $model->user->id) {
                    $this->safeSend($client, [
                        'type' => 'store.return.item',
                        'code' => 500,
                        'message' => Yii::t('common', "Товар вам не принадлежит!", [], $client->user->current_language),
                        'id' => $model->id,
                    ]);
                    return;
                }

                // Атомарная блокировка предмета на время обработки (используем общий ключ для getDrop и returnDrop)
                $lockKey = 'userDrop_lock_' . $model->id;
                if (Yii::$app->cache->get($lockKey)) {
                    $this->safeSend($client, [
                        'type' => 'store.return.item',
                        'code' => 500,
                        'message' => Yii::t('common', "Предмет уже обрабатывается, подождите немного!", [], $client->user->current_language),
                        'id' => $model->id,
                    ]);
                    return;
                }
                Yii::$app->cache->set($lockKey, true, 10); // Блокируем на 10 секунд

                // Перезагружаем модель после блокировки для проверки актуального статуса
                $model->refresh();

                if (!empty($model->box_id) || !empty($model->sets_id) || !empty($model->parent_drop_id)) {
                    Yii::$app->cache->delete($lockKey); // Снимаем блокировку
                    $this->safeSend($client, [
                        'type' => 'store.return.item',
                        'code' => 500,
                        'message' => Yii::t('common', "Не подлежит возврату!", [], $client->user->current_language),
                        'id' => $model->id,
                    ]);
                    return;
                }

                if ($model->status !== UserDrop::STATUS_ACTIVE) {
                    Yii::$app->cache->delete($lockKey); // Снимаем блокировку
                    $this->safeSend($client, [
                        'type' => 'store.return.item',
                        'code' => 500,
                        'message' => Yii::t('common', "Не найдена вещь в корзине!", [], $client->user->current_language),
                        'id' => $model->id,
                    ]);
                    return;
                }

                // Выполняем возврат
                try {
                    $userBalance = $model->user->getPersonalBalance();
                    $this->_sellUserDrop($model, $userBalance->id);

                    // Отправляем через websocket
                    try {
                        Yii::$app->queueProcess->push(new ReturnDropJob(['userDrop' => $model]));
                    } catch (\Throwable $e) {
                        $this->log("Error pushing ReturnDropJob to queue: " . $e->getMessage());
                        // Не прерываем выполнение, так как возврат уже выполнен
                    }

                    Yii::$app->cache->delete($lockKey); // Снимаем блокировку после успешного возврата

                    $this->safeSend($client, [
                        'type' => 'store.return.item',
                        'code' => 200,
                        'message' => Yii::t('common', "Предмет успешно возвращен!", [], $client->user->current_language),
                        'id' => $model->id,
                    ]);
                } catch (\Exception $e) {
                    Yii::$app->cache->delete($lockKey); // Снимаем блокировку при ошибке
                    throw $e;
                }
            }
        } catch (\Exception $ex) {
            $this->log("ReturnDrop error: " . $ex->getMessage());
            if (!empty($request['id'])) {
                $this->safeSend($client, [
                    'type' => 'store.return.item',
                    'code' => 500,
                    'message' => Yii::t('common', "Произошла ошибка при возврате товара!", [], !empty($client->user) ? $client->user->current_language : 'ru-RU'),
                    'id' => $request['id'],
                ]);
            }
        }
    }

    /**
     * Продажа товара (возврат)
     * @param UserDrop $userDrop
     * @param int $userBalanceId
     */
    private function _sellUserDrop($userDrop, $userBalanceId)
    {
        // Используем dropOne для получения одного предмета
        $drop = $userDrop->dropOne;

        if (empty($drop)) {
            // Если dropOne не найден, пытаемся загрузить через drop_id
            $drop = \common\models\box\Drop::findOne($userDrop->drop_id);
        }

        if (empty($drop)) {
            $this->log("Drop not found for UserDrop ID: {$userDrop->id}, drop_id: {$userDrop->drop_id}");
            throw new \Exception('Предмет не найден');
        }

        $profit = new Profit();
        $profit->status = 1;
        $profit->type = Profit::TYPE_SELL_DROP;
        $profit->amount = $drop->getRealPrice(false);
        $profit->user_balance_id = $userBalanceId;
        $profit->comment = Yii::t('common', 'Возврат предмета "{PARAMS_PREDNAME}"', [
            'PARAMS_PREDNAME' => Yii::t('database', $drop->name)
        ], 'ru-RU');
        $profit->created_at = date('Y-m-d H:i:s');

        if (!$profit->save(false)) {
            $this->log("Failed to save profit for UserDrop ID: {$userDrop->id}, errors: " . json_encode($profit->getErrors()));
            throw new \Exception('Ошибка при сохранении возврата');
        }

        $userDrop->status = UserDrop::STATUS_SELL;
        if (!$userDrop->save(false)) {
            $this->log("Failed to save UserDrop ID: {$userDrop->id}, errors: " . json_encode($userDrop->getErrors()));
            throw new \Exception('Ошибка при обновлении статуса товара');
        }
        $userDrop->save(false);
    }

    public function commandActivatedDrop(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        $result = ['message' => ''];

        if (!empty($request['id'])) {
           $model = UserDrop::findOne($request['id']);
           if (!$model) {
               $client->send(json_encode($result));
               return;
           }

           // Используем индекс для быстрого поиска клиентов пользователя
           $userClients = $this->getClientsByUserId($model->user->id);

           if ($request['code'] == 200) {
               $response = json_encode([
                   'type'    => 'store.get.items',
                   'code'    => 200,
                   'message' => Yii::t('common', "Товар успешно получен!", [], $model->user->current_language),
                   'id'      => $request['id'],
               ]);
           } else {
               $response = json_encode([
                   'type'    => 'store.get.items',
                   'code'    => 500,
                   'message' => $request['message'],
                   'id'      => $request['id'],
               ]);
           }

           foreach ($userClients as $chatClient) {
               try {
                   $chatClient->send($response);
               } catch (\Exception $ex) {
                   $this->log("Error sending activated drop: " . $ex->getMessage());
               }
           }
        }

        $client->send(json_encode($result));
    }

    public function commandUpdatedBalance(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        $result = ['message' => ''];

        if (!empty($request['user_id']) && $request['code'] == 200) {
           $hash = md5(time());

           // Используем индекс для быстрого поиска клиентов пользователя
           $userClients = $this->getClientsByUserId($request['user_id']);
           $response = json_encode([
               'type'       => 'update.balance',
               'code'       => 200,
               'balanceStr' => $request['balanceStr'],
               'balance'    => $request['balance'],
               'hash'       => $hash,
           ]);

           foreach ($userClients as $chatClient) {
               try {
                   $chatClient->send($response);
               } catch (\Exception $ex) {
                   $this->log("Error sending balance update: " . $ex->getMessage());
               }
           }
        }

        $client->send(json_encode($result));
    }

    public function commandUpdatedOnline(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        $result = ['message' => ''];

        if ($request['code'] == 200) {
            $response = json_encode([
                'type'    => 'update.online',
                'code'    => 200,
                'servers' => $request['servers'],
                'total'   => $request['total'],
            ]);

            // Отправляем всем клиентам (broadcast)
            foreach ($this->clients as $chatClient) {
                try {
                    $chatClient->send($response);
                } catch (\Exception $ex) {
                    // Молча пропускаем ошибки для broadcast сообщений
                }
            }
        }

        $client->send( json_encode($result) );
    }

    /**
     * Статический метод для отправки обновлений онлайна без создания WebSocket клиента
     * Используется в Servers::notify() для избежания rate limiting
     */
    public static function broadcastOnlineUpdate($serversData, $total)
    {
        try {
            $cacheKey = 'ws_online_data';
            Yii::$app->cache->set($cacheKey, [
                'servers' => $serversData,
                'total' => $total,
                'timestamp' => time(),
            ], 10);

            return true;
        } catch (\Exception $ex) {
            return false;
        }
    }

    /**
     * Отправка supportStatus без создания WebSocket клиента
     */
    public static function broadcastSupportStatus($ticketNumber)
    {
        try {
            $cacheKey = 'ws_support_status_' . $ticketNumber;
            Yii::$app->cache->set($cacheKey, [
                'action' => 'supportStatus',
                'code' => 200,
                'id' => $ticketNumber,
                'timestamp' => time(),
            ], 10);

            return true;
        } catch (\Exception $ex) {
            return false;
        }
    }

    /**
     * Отправка ticketUpdate без создания WebSocket клиента
     */
    public static function broadcastTicketUpdate($userId)
    {
        try {
            $cacheKey = 'ws_ticket_update_' . $userId;
            Yii::$app->cache->set($cacheKey, [
                'action' => 'ticketUpdate',
                'code' => 200,
                'user_id' => $userId,
                'timestamp' => time(),
            ], 10);

            return true;
        } catch (\Exception $ex) {
            return false;
        }
    }

    /**
     * Отправка chatUpdate без создания WebSocket клиента
     */
    public static function broadcastChatUpdate($ticketNumber, $userId, $messageId)
    {
        try {
            // Основной ключ для последнего сообщения чата
            $cacheKey = 'ws_chat_update_' . $ticketNumber;
            Yii::$app->cache->set($cacheKey, [
                'action' => 'chatUpdate',
                'code' => 200,
                'id' => $ticketNumber,
                'user_id' => $userId,
                'messageId' => $messageId,
                'timestamp' => time(),
            ], 10);

            // Также сохраняем с messageId для обратной совместимости
            $cacheKeyWithId = 'ws_chat_update_' . $ticketNumber . '_' . $messageId;
            Yii::$app->cache->set($cacheKeyWithId, [
                'action' => 'chatUpdate',
                'code' => 200,
                'id' => $ticketNumber,
                'user_id' => $userId,
                'messageId' => $messageId,
                'timestamp' => time(),
            ], 10);

            return true;
        } catch (\Exception $ex) {
            return false;
        }
    }



    /**
     * Отправка launcherUpdate без создания WebSocket клиента
     */
    public static function broadcastLauncherUpdate()
    {
        try {
            $cacheKey = 'ws_launcher_update_' . time();
            Yii::$app->cache->set($cacheKey, [
                'action' => 'launcherUpdate',
                'code' => 200,
                'timestamp' => time(),
            ], 10);

            return true;
        } catch (\Exception $ex) {
            return false;
        }
    }

    public function commandChatFocus(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        if (empty($client->chat) || empty($client->user) || empty($request['chatId'])) {
            return;
        }

        try {
            // Используем индекс для быстрого поиска клиентов по chat
            $chatClients = $this->getClientsByChat($request['chatId']);
            $response = json_encode([
                'type' => 'chatFocus',
                'content' => "Пользователь {$client->user->username} печатает сообщение...",
            ]);

            foreach ($chatClients as $chatClient) {
                if ($chatClient !== $client && !empty($chatClient->user)) {
                    try {
                        $chatClient->send($response);
                    } catch (\Exception $ex) {
                        $this->log("Error sending chat focus: " . $ex->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            $this->log("commandChatFocus error: " . $e->getLine() . ":" . $e->getMessage());
        }
        // Не шлём пустой ответ
    }

    public function commandChatBlur(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        if (empty($client->chat) || empty($client->user) || empty($request['chatId'])) {
            return;
        }

        try {
            $chatClients = $this->getClientsByChat($request['chatId']);
            $response = json_encode(['type' => 'chatBlur']);

            foreach ($chatClients as $chatClient) {
                if ($chatClient !== $client && !empty($chatClient->user)) {
                    try {
                        $chatClient->send($response);
                    } catch (\Exception $ex) {
                        $this->log("Error sending chat blur: " . $ex->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            $this->log("commandChatBlur error: " . $e->getLine() . ":" . $e->getMessage());
        }
        // Не шлём пустой ответ инициатору
    }

    public static function usernameClass($user) {
        if ($user->canRoles([Role::ROLE_ADMIN])) {
            return 'admin';
        }
        if ($user->canRoles([Role::ROLE_MODERATOR]) || $user->canRoles([Role::ROLE_SUPPORT])) {
            return 'moder';
        }
        return '';
    }

    public function commandChat(ConnectionInterface $client, $msg)
    {
        try {
            $request = json_decode($msg, true);
            $result = ['message' => ''];

            // Если client->chat не установлен, но есть chatId в запросе, устанавливаем его
            if (empty($client->chat) && !empty($request['chatId'])) {
                $client->chat = (string)$request['chatId'];
                $this->indexClientByChat($client);
            }

            if (empty($client->chat)) {
                $errorResponse = ['type' => 'error', 'error' => 'Chat not subscribed'];
                if (!empty($request['tempId'])) {
                    $errorResponse['tempId'] = $request['tempId'];
                }
                $client->send(json_encode($errorResponse));
                return;
            }

            // Нормализуем chatId из запроса для сравнения
            $requestChatId = !empty($request['chatId']) ? (string)$request['chatId'] : (string)$client->chat;

            if (!empty($client->user) && !empty($request['message']) && $message = trim($request['message']) ) {
                $cacheKey = 'commandChat_' . $client->user->id;
                if (!empty(Yii::$app->cache->get($cacheKey))) {
                    $errorResponse = ['type' => 'error', 'error' => Yii::$app->cache->get($cacheKey)];
                    // Добавляем tempId в ответ об ошибке, если он был передан
                    if (!empty($request['tempId'])) {
                        $errorResponse['tempId'] = $request['tempId'];
                    }
                    $client->send(json_encode($errorResponse));
                    return;
                }
                if (!$client->user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT])) {
                    Yii::$app->cache->set($cacheKey, Yii::t('common', "Нельзя отправлять сообщения слишком часто!", [], $client->user->current_language), 2);
                }
                /** @var User $user */
                $user = $client->user;
                if ($user->blocked_support || $user->status == User::STATUS_BLOCKED || strtotime($user->blocked_support_at) > time()) {
                    $errorResponse = ['type' => 'error', 'error' => Yii::t('common', "Ваш чат заблокирован")];
                    // Добавляем tempId в ответ об ошибке, если он был передан
                    if (!empty($request['tempId'])) {
                        $errorResponse['tempId'] = $request['tempId'];
                    }
                    $client->send(json_encode($errorResponse));
                    return;
                }
                // Используем нормализованный chatId
                $chatNumber = (string)$client->chat;
                $chat = Support::findByNumber($chatNumber);
                if (empty($chat)) {
                    $chat = new Support();
                    $chat->user_id = $user->id;
                    $chat->status = Support::STATUS_OPEN;
                    $chat->server_tag = !empty($user->server_id) ? $user->server->tag : null;
                    $chat->created_at = date('Y-m-d H:i:s');
                    $chat->updated_at = date('Y-m-d H:i:s');
                    $chat->save(false);
                    $mModel = new SupportMessage();
                    $mModel->user_id = null;
                    $mModel->message = "{USER_INFO}";
                    $mModel->support_id = $chat->id;
                    $mModel->created_at = date('Y-m-d H:i:s');
                    $mModel->save();
                    $client->send(json_encode(['type' => 'redirect', 'url' => $chat->getUrl()]));
                } else {
                    $chat->updated_at = date('Y-m-d H:i:s');
                    $chat->save(false);
                }
                // Проверяем, является ли сообщение стикером
                $isSticker = preg_match('/^<(img|video)[^>]*class="[^"]*support_sticker[^"]*"[^>]*>.*<\/(img|video)>$/', trim($message));

                if ($isSticker) {
                    // Для стикеров не применяем htmlspecialchars и HtmlPurifier
                    $message = trim($message);
                } else {
                    // Для обычных сообщений применяем стандартную обработку
                    $message = htmlspecialchars(\yii\helpers\HtmlPurifier::process(trim($message)));
                }
                $model = new SupportMessage();
                $model->user_id = $user->id;
                $model->message = trim($message);
                $model->support_id = $chat->id;
                $model->created_at = date('Y-m-d H:i:s');

                // Убеждаемся, что id не установлен (должен быть AUTO_INCREMENT)
                if (isset($model->id)) {
                    unset($model->id);
                }

                // Сохраняем сообщение с обработкой ошибок
                try {
                    if (!$model->save()) {
                        $errorResponse = ['type' => 'error', 'error' => Yii::t('common', "Ошибка сохранения сообщения")];
                        if (!empty($request['tempId'])) {
                            $errorResponse['tempId'] = $request['tempId'];
                        }
                        $client->send(json_encode($errorResponse));
                        $this->log("commandChat: Failed to save message. Errors: " . json_encode($model->errors));
                        return;
                    }
                } catch (\Exception $e) {
                    $errorResponse = ['type' => 'error', 'error' => Yii::t('common', "Ошибка сохранения сообщения")];
                    if (!empty($request['tempId'])) {
                        $errorResponse['tempId'] = $request['tempId'];
                    }
                    $client->send(json_encode($errorResponse));
                    $this->log("commandChat: Exception saving message: " . $e->getMessage());
                    return;
                }

                Yii::$app->queueProcess->push(new BeforeMessageJob([
                    'chatId' => $model->support_id,
                    'userId' => $model->user_id,
                    'message' => $model->message,
                    'username' => $user->username,
                    'chatNumber' => $chat->getNumber(),
                ]));

                SupportRead::createRecord($chat->user_id, $user->id, $model->id, $chat->id);
                $hash = md5(time());
                $tempId = !empty($request['tempId']) ? $request['tempId'] : null;
                $chatResponse = [
                    'type' => 'chat',
                    'messageId' => $model->id,
                ];
                if ($tempId) {
                    $chatResponse['tempId'] = $tempId;
                }
                $chatResponseJson = json_encode($chatResponse);
                $ticketsUpdateJson = json_encode(['type' => 'ticketsUpdate']);
                $chatNumber = $chat->getNumber();

                // Отправка ответа по чату только подписчикам этого чата
                $chatClients = $this->getClientsByChat($requestChatId);
                foreach ($chatClients as $chatClient) {
                    if (empty($chatClient->user)) continue;
                    SupportRead::readedAll($model->support_id, $chatClient->user->id);
                    try {
                        $chatClient->send($chatResponseJson);
                    } catch (\Exception $ex) {
                        $this->log("Error sending chat message to client: " . $ex->getMessage());
                    }
                }
                $this->statsChatSent++;

                // Собираем owner + staff для ticketsUpdate и support_notifications (заглушка count = "!" — запрос отключён)
                $userIdsForNotify = [];
                $staffUserIds = [];
                foreach ($this->clients as $chatClient) {
                    if (empty($chatClient->user)) continue;
                    $_user = $chatClient->user;
                    $isOwner = ($_user->id === $chat->user_id);
                    $isStaff = $_user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT]);
                    if ($isStaff) {
                        $staffUserIds[$_user->id] = true;
                    }
                    if (($_user->id !== $user->id) && ($isOwner || $isStaff)) {
                        $userIdsForNotify[$_user->id] = true;
                    }
                }

                foreach ($this->clients as $chatClient) {
                    if (empty($chatClient->user)) continue;
                    $_user = $chatClient->user;
                    $isOwner = ($_user->id === $chat->user_id);
                    $isStaff = isset($staffUserIds[$_user->id]);
                    if ($isOwner && !empty($chatClient->chat)) {
                        try { $chatClient->send($ticketsUpdateJson); } catch (\Exception $e) {}
                    }
                    if ($isStaff && !$isOwner) {
                        try { $chatClient->send($ticketsUpdateJson); } catch (\Exception $e) {}
                    }
                    if (isset($userIdsForNotify[$_user->id]) && $_user->id !== $user->id) {
                        try {
                            $chatClient->send(json_encode([
                                'type' => 'support_notifications',
                                'count' => 1,
                                'chatId' => $chatNumber,
                                'hash'   => $hash,
                            ]));
                        } catch (\Exception $ex) {
                            $this->log("Error sending support notification: " . $ex->getMessage());
                        }
                    }
                }
            } else {
                $result['message'] = 'Enter message';
            }

            //$client->send( json_encode($result) );
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('commandChat: ' . $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage());
        }
    }

    /**
     * Проверяет, является ли ошибка ошибкой "MySQL server has gone away" или проблемой подключения
     * @param \Exception $e Исключение для проверки
     * @return bool
     */
    private function isGoneAwayError(\Exception $e)
    {
        $message = $e->getMessage();
        $code = $e->getCode();

        // Проверяем код ошибки 2006 или текст сообщения
        return $code == 2006 ||
               strpos($message, '2006') !== false ||
               strpos($message, 'MySQL server has gone away') !== false ||
               strpos($message, 'server has gone away') !== false ||
               strpos($message, 'HY000') !== false && strpos($message, '2006') !== false ||
               strpos($message, 'No such file or directory') !== false ||
               strpos($message, '[2002]') !== false;
    }

    /**
     * Переподключается к базе данных
     * @return bool Успешно ли переподключение
     */
    private function reconnectDatabase()
    {
        try {
            // Закрываем текущее соединение
            if (Yii::$app->db->isActive) {
                Yii::$app->db->close();
            }

            // Небольшая задержка перед переподключением
            usleep(50000); // 0.05 секунды

            // Переподключаемся
            Yii::$app->db->open();

            // Проверяем, что соединение действительно установлено
            if (Yii::$app->db->isActive) {
                $this->log("Database reconnected successfully");
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->log("Failed to reconnect: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Безопасное выполнение запроса к БД с автоматическим переподключением
     * @param callable $callback Функция, выполняющая запрос к БД
     * @param int $maxRetries Максимальное количество попыток
     * @return mixed Результат выполнения callback
     * @throws \Exception
     */
    private function safeDbQuery(callable $callback, $maxRetries = 3)
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                return $callback();
            } catch (DbException $e) {
                $lastException = $e;
                $attempt++;

                // Проверяем, является ли это ошибкой "MySQL server has gone away"
                if ($this->isGoneAwayError($e)) {
                    $this->log("Database connection lost (attempt {$attempt}/{$maxRetries}): " . $e->getMessage());

                    // Пытаемся переподключиться
                    if ($this->reconnectDatabase()) {
                        // Если переподключились успешно, пробуем снова
                        continue;
                    } else {
                        // Если не удалось переподключиться, ждем немного и пробуем еще раз
                        if ($attempt < $maxRetries) {
                            usleep(200000); // 0.2 секунды
                            continue;
                        }
                    }
                }

                // Если это не ошибка переподключения или попытки закончились, пробрасываем исключение
                throw $e;
            } catch (PDOException $e) {
                $lastException = $e;
                $attempt++;

                // Проверяем, является ли это ошибкой "MySQL server has gone away"
                if ($this->isGoneAwayError($e)) {
                    $this->log("Database connection lost (PDO, attempt {$attempt}/{$maxRetries}): " . $e->getMessage());

                    // Пытаемся переподключиться
                    if ($this->reconnectDatabase()) {
                        // Если переподключились успешно, пробуем снова
                        continue;
                    } else {
                        // Если не удалось переподключиться, ждем немного и пробуем еще раз
                        if ($attempt < $maxRetries) {
                            usleep(200000); // 0.2 секунды
                            continue;
                        }
                    }
                }

                // Если это не ошибка переподключения или попытки закончились, пробрасываем исключение
                throw $e;
            } catch (\Exception $e) {
                // Для других исключений проверяем, может быть это тоже ошибка БД
                if ($this->isGoneAwayError($e)) {
                    $lastException = $e;
                    $attempt++;
                    $this->log("Database connection lost (generic, attempt {$attempt}/{$maxRetries}): " . $e->getMessage());

                    if ($this->reconnectDatabase() && $attempt < $maxRetries) {
                        continue;
                    }
                }

                // Для всех остальных исключений пробрасываем сразу
                throw $e;
            }
        }

        // Если все попытки исчерпаны, бросаем последнее исключение
        if ($lastException) {
            throw $lastException;
        }

        throw new \Exception("Failed to execute database query after {$maxRetries} attempts");
    }

    public function commandAuth(ConnectionInterface $client, $msg)
    {
        try {
            $request = json_decode($msg, true);
            $result = [];

            // Валидация входных данных
            if (empty($request['token']) || empty($request['steam_id'])) {
                $result['message'] = 'Invalid token';
                $clientIp = isset($client->realIp) ? $client->realIp : $client->remoteAddress;
                $this->log("Auth failed: missing token or steam_id from " . $clientIp);
                $this->safeSend($client, $result);
                return;
            }

            // Кешируем запрос пользователя на 60 секунд
            $cacheKey = 'ws_auth_' . md5($request['token'] . $request['steam_id']);

            try {
                $user = Yii::$app->cache->getOrSet($cacheKey, function() use ($request) {
                    // Используем безопасный запрос с автоматическим переподключением
                    return $this->safeDbQuery(function() use ($request) {
                        return User::find()
                            ->where(['jwt' => $request['token'], 'steam_id' => $request['steam_id']])
                            ->limit(1)
                            ->one();
                    });
                }, 60);
            } catch (\Exception $cacheEx) {
                // Если ошибка при работе с кешем, пробуем напрямую запросить из БД
                $this->log("Cache error in commandAuth, trying direct DB query: " . $cacheEx->getMessage());
                try {
                    $user = $this->safeDbQuery(function() use ($request) {
                        return User::find()
                            ->where(['jwt' => $request['token'], 'steam_id' => $request['steam_id']])
                            ->limit(1)
                            ->one();
                    });
                } catch (\Exception $dbEx) {
                    // Если и прямой запрос не удался, логируем и возвращаем null
                    $this->log("Auth error: " . $dbEx->getMessage());
                    $user = null;
                }
            }

            if ($user) {
                $client->user = $user;
                if (isset($request['launcher'])) {
                    $client->launcher = $request['launcher'];
                }

                // Добавляем в индекс для быстрого поиска
                $this->indexClientByUserId($client);

                $clientIp = isset($client->realIp) ? $client->realIp : $client->remoteAddress;

            } else {
                $result['message'] = 'Invalid token';
                $clientIp = isset($client->realIp) ? $client->realIp : $client->remoteAddress;
                $this->log("Auth failed for token/steam_id from " . $clientIp);
                $this->safeSend($client, $result);
            }
        } catch (\Exception $ex) {
            $this->log("Auth error: " . $ex->getMessage());
            $errorMessage = 'commandAuth: ' . $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage();

            // Если это ошибка БД, пытаемся переподключиться
            if ($this->isGoneAwayError($ex)) {
                $this->reconnectDatabase();
            }

            // Отправляем ошибку в Telegram только если это не ошибка переподключения
            if (!$this->isGoneAwayError($ex)) {
                try {
                    Yii::$app->telegramChats->sendMessage($errorMessage);
                } catch (\Exception $telegramEx) {
                    $this->log("Failed to send error to Telegram: " . $telegramEx->getMessage());
                }
            }

            // Отправляем ответ клиенту
            $result['message'] = 'Invalid token';
            $this->safeSend($client, $result);
        }
    }
    public function commandPong(ConnectionInterface $client, $msg)
    {
        try {
            $client->lastPong = time();
            $client->alive = true;
            // лог для поиска причин // NEW
            // $this->log("pong from client");
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('commandPong: ' . $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage());
        }
    }
}
