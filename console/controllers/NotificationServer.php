<?php
namespace console\controllers;

use common\models\user\User;
use common\models\support\Support;
use common\components\helpers\Role;
use consik\yii2websocket\events\WSClientEvent;
use consik\yii2websocket\WebSocketServer;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\Version\RFC6455\Frame;
use Yii;
use api\components\jwt\JwtService;

class NotificationServer extends WebSocketServer
{
    /** @var NotificationServer Singleton instance */
    private static $instance = null;
    
    /** @var JwtService */
    private $jwtService;
    
    /** @var array Индекс клиентов по user_id */
    private $clientsByUserId = [];
    
    /** @var array Индекс клиентов по ticket_id */
    private $clientsByTicketId = [];
    
    /** @var int Таймаут авторизации в секундах */
    public $authTimeout = 10;
    
    /** @var int Таймаут idle-соединения в секундах */
    public $idleCloseSeconds = 300;

    /**
     * Получить singleton instance
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    /**
     * Индексировать клиента по user_id
     */
    private function indexClientByUserId(ConnectionInterface $client)
    {
        if (!empty($client->user) && !empty($client->user->id)) {
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
     * Удалить клиента из индексов
     */
    private function removeClientFromIndexes(ConnectionInterface $client)
    {
        // Удаляем из индекса по user_id
        if (!empty($client->user) && !empty($client->user->id)) {
            $userId = $client->user->id;
            if (isset($this->clientsByUserId[$userId])) {
                $key = array_search($client, $this->clientsByUserId[$userId], true);
                if ($key !== false) {
                    unset($this->clientsByUserId[$userId][$key]);
                    if (empty($this->clientsByUserId[$userId])) {
                        unset($this->clientsByUserId[$userId]);
                    }
                }
            }
        }

        // Удаляем из индекса по ticket_id
        $subscribedTickets = isset($client->subscribedTickets) ? (array)$client->subscribedTickets : [];
        if (!empty($subscribedTickets)) {
            foreach ($subscribedTickets as $ticketId) {
                if (isset($this->clientsByTicketId[$ticketId])) {
                    $key = array_search($client, $this->clientsByTicketId[$ticketId], true);
                    if ($key !== false) {
                        unset($this->clientsByTicketId[$ticketId][$key]);
                        if (empty($this->clientsByTicketId[$ticketId])) {
                            unset($this->clientsByTicketId[$ticketId]);
                        }
                    }
                }
            }
        }
    }

    public function init()
    {
        parent::init();
        
        // Устанавливаем singleton instance
        self::$instance = $this;

        // Инициализируем JWT сервис (используем компонент из API конфигурации через console)
        // В console приложении нужно использовать прямой доступ к компоненту
        // Создаем экземпляр JwtService напрямую
        $this->jwtService = new \api\components\jwt\JwtService([
            'secret' => Yii::$app->params['jwt']['secret'] ?? getenv('JWT_SECRET'),
            'algorithm' => Yii::$app->params['jwt']['algorithm'] ?? 'HS256',
            'expiration' => Yii::$app->params['jwt']['expiration'] ?? 3600,
            'refreshExpiration' => Yii::$app->params['jwt']['refreshExpiration'] ?? 604800,
        ]);
        $this->jwtService->init();

        $this->on(self::EVENT_CLIENT_CONNECTED, function(WSClientEvent $e) {
            $this->log("Client connected: " . $e->client->remoteAddress);
            $client = $e->client;
            $client->user = null;
            $client->authenticated = false;
            $client->connectedAt = time(); // Время подключения
            
            // Инициализируем subscribedTickets как пустой массив
            if (!isset($client->subscribedTickets)) {
                $client->subscribedTickets = [];
            }

            // Инициализация клиента
        });

        $this->on(self::EVENT_CLIENT_DISCONNECTED, function(WSClientEvent $e) {
            $userId = !empty($e->client->user) ? $e->client->user->id : 'anonymous';
            $this->log("Client disconnected: {$userId} from " . $e->client->remoteAddress);
            
            // Удаляем из индексов
            $this->removeClientFromIndexes($e->client);
        });

        // Настраиваем heartbeat и проверку авторизации
        $this->on(self::EVENT_WEBSOCKET_OPEN, function () {
            /** @var \Ratchet\Server\IoServer $io */
            $io = $this->server;
            $loop = $io->loop;

            // Обработка сообщений поддержки из кеша каждую секунду
            $loop->addPeriodicTimer(1, function () {
                try {
                    $now = time();
                    
                    // Получаем очередь тикетов с новыми сообщениями
                    $queueKey = 'ws_support_messages_queue';
                    $queue = Yii::$app->cache->get($queueKey);
                    if (!is_array($queue)) {
                        $queue = [];
                    }
                    
                    // Обрабатываем тикеты из очереди
                    $processedTickets = [];
                    foreach ($queue as $ticketId => $queueTimestamp) {
                        // Проверяем только свежие сообщения (не старше 5 секунд)
                        if (($now - $queueTimestamp) > 5) {
                            continue;
                        }
                        
                        $cacheKey = 'ws_support_message_' . $ticketId;
                        $cachedData = Yii::$app->cache->get($cacheKey);
                        
                        if ($cachedData && isset($cachedData['type']) && $cachedData['type'] === 'support.message') {
                            // Проверяем, что данные свежие (не старше 5 секунд)
                            if (isset($cachedData['timestamp']) && ($now - $cachedData['timestamp']) < 5) {
                                // Удаляем из кеша
                                Yii::$app->cache->delete($cacheKey);
                                
                                // Отправляем сообщение (админы и владельцы получат автоматически)
                                $this->broadcastNewMessage(
                                    $cachedData['ticketId'],
                                    $cachedData['messageId'],
                                    $cachedData['userId'],
                                    $cachedData['ownerUserId'] ?? null
                                );
                                
                                $processedTickets[] = $ticketId;
                            }
                        }
                    }
                    
                    // Удаляем обработанные тикеты из очереди
                    if (!empty($processedTickets)) {
                        foreach ($processedTickets as $ticketId) {
                            unset($queue[$ticketId]);
                        }
                        if (empty($queue)) {
                            Yii::$app->cache->delete($queueKey);
                        } else {
                            Yii::$app->cache->set($queueKey, $queue, 10);
                        }
                    }
                    
                    // Обработка уведомлений о новых тикетах
                    $newTicketsQueueKey = 'ws_support_new_tickets_queue';
                    $newTicketsQueue = Yii::$app->cache->get($newTicketsQueueKey);
                    if (!is_array($newTicketsQueue)) {
                        $newTicketsQueue = [];
                    }
                    
                    $processedNewTickets = [];
                    foreach ($newTicketsQueue as $ticketId => $queueTimestamp) {
                        // Проверяем только свежие уведомления (не старше 5 секунд)
                        if (($now - $queueTimestamp) > 5) {
                            continue;
                        }
                        
                        $newTicketCacheKey = 'ws_support_new_ticket_' . $ticketId;
                        $newTicketData = Yii::$app->cache->get($newTicketCacheKey);
                        
                        if ($newTicketData && isset($newTicketData['type']) && $newTicketData['type'] === 'support.new_ticket') {
                            // Проверяем, что данные свежие (не старше 5 секунд)
                            if (isset($newTicketData['timestamp']) && ($now - $newTicketData['timestamp']) < 5) {
                                // Удаляем из кеша
                                Yii::$app->cache->delete($newTicketCacheKey);
                                
                                // Отправляем уведомление о новом тикете
                                $this->broadcastNewTicketNotification(
                                    $newTicketData['ticketId'],
                                    $newTicketData['userId']
                                );
                                
                                $processedNewTickets[] = $ticketId;
                            }
                        }
                    }
                    
                    // Удаляем обработанные тикеты из очереди новых тикетов
                    if (!empty($processedNewTickets)) {
                        foreach ($processedNewTickets as $ticketId) {
                            unset($newTicketsQueue[$ticketId]);
                        }
                        if (empty($newTicketsQueue)) {
                            Yii::$app->cache->delete($newTicketsQueueKey);
                        } else {
                            Yii::$app->cache->set($newTicketsQueueKey, $newTicketsQueue, 10);
                        }
                    }
                    
                    // Обработка уведомлений о статусах тикетов
                    $statusQueueKey = 'ws_support_status_queue';
                    $statusQueue = Yii::$app->cache->get($statusQueueKey);
                    if (!is_array($statusQueue)) {
                        $statusQueue = [];
                    }
                    
                    $processedStatusTickets = [];
                    foreach ($statusQueue as $ticketId => $queueTimestamp) {
                        // Проверяем только свежие уведомления (не старше 5 секунд)
                        if (($now - $queueTimestamp) > 5) {
                            continue;
                        }
                        
                        $statusCacheKey = 'ws_support_status_' . $ticketId;
                        $statusData = Yii::$app->cache->get($statusCacheKey);
                        
                        if ($statusData && isset($statusData['type']) && $statusData['type'] === 'support.status') {
                            // Проверяем, что данные свежие (не старше 5 секунд)
                            if (isset($statusData['timestamp']) && ($now - $statusData['timestamp']) < 5) {
                                // Удаляем из кеша
                                Yii::$app->cache->delete($statusCacheKey);
                                
                                // Отправляем уведомление о статусе
                                $this->broadcastTicketStatusUpdate(
                                    $statusData['ticketId'],
                                    $statusData['status']
                                );
                                
                                $processedStatusTickets[] = $ticketId;
                            }
                        }
                    }
                    
                    // Удаляем обработанные тикеты из очереди статусов
                    if (!empty($processedStatusTickets)) {
                        foreach ($processedStatusTickets as $ticketId) {
                            unset($statusQueue[$ticketId]);
                        }
                        if (empty($statusQueue)) {
                            Yii::$app->cache->delete($statusQueueKey);
                        } else {
                            Yii::$app->cache->set($statusQueueKey, $statusQueue, 10);
                        }
                    }
                    
                    // Обработка уведомлений об обновлении сообщений
                    $messageUpdatesQueueKey = 'ws_support_message_updates_queue';
                    $messageUpdatesQueue = Yii::$app->cache->get($messageUpdatesQueueKey);
                    if (!is_array($messageUpdatesQueue)) {
                        $messageUpdatesQueue = [];
                    }
                    
                    $processedMessageUpdates = [];
                    foreach ($messageUpdatesQueue as $queueKeyId => $queueTimestamp) {
                        // Проверяем только свежие уведомления (не старше 5 секунд)
                        if (($now - $queueTimestamp) > 5) {
                            continue;
                        }
                        
                        // queueKeyId имеет формат "ticketId_messageId"
                        list($ticketId, $messageId) = explode('_', $queueKeyId, 2);
                        $updateCacheKey = 'ws_support_message_update_' . $ticketId . '_' . $messageId;
                        $updateData = Yii::$app->cache->get($updateCacheKey);
                        
                        if ($updateData && isset($updateData['type']) && $updateData['type'] === 'support.message.updated') {
                            // Проверяем, что данные свежие (не старше 5 секунд)
                            if (isset($updateData['timestamp']) && ($now - $updateData['timestamp']) < 5) {
                                // Удаляем из кеша
                                Yii::$app->cache->delete($updateCacheKey);
                                
                                // Отправляем уведомление об обновлении
                                $this->sendMessageUpdateNotification(
                                    $updateData['ticketId'],
                                    $updateData['messageId']
                                );
                                
                                $processedMessageUpdates[] = $queueKeyId;
                            }
                        }
                    }
                    
                    // Удаляем обработанные обновления из очереди
                    if (!empty($processedMessageUpdates)) {
                        foreach ($processedMessageUpdates as $queueKeyId) {
                            unset($messageUpdatesQueue[$queueKeyId]);
                        }
                        if (empty($messageUpdatesQueue)) {
                            Yii::$app->cache->delete($messageUpdatesQueueKey);
                        } else {
                            Yii::$app->cache->set($messageUpdatesQueueKey, $messageUpdatesQueue, 10);
                        }
                    }
                    
                    // Обработка уведомлений об удалении сообщений
                    $messageDeletesQueueKey = 'ws_support_message_deletes_queue';
                    $messageDeletesQueue = Yii::$app->cache->get($messageDeletesQueueKey);
                    if (!is_array($messageDeletesQueue)) {
                        $messageDeletesQueue = [];
                    }
                    
                    $processedMessageDeletes = [];
                    foreach ($messageDeletesQueue as $queueKeyId => $queueTimestamp) {
                        // Проверяем только свежие уведомления (не старше 5 секунд)
                        if (($now - $queueTimestamp) > 5) {
                            continue;
                        }
                        
                        // queueKeyId имеет формат "ticketId_messageId"
                        $parts = explode('_', $queueKeyId, 2);
                        if (count($parts) !== 2) {
                            $this->log("Invalid queueKeyId format for delete: {$queueKeyId}");
                            continue;
                        }
                        list($ticketId, $messageId) = $parts;
                        $deleteCacheKey = 'ws_support_message_delete_' . $ticketId . '_' . $messageId;
                        $deleteData = Yii::$app->cache->get($deleteCacheKey);
                        
                        if ($deleteData && isset($deleteData['type']) && $deleteData['type'] === 'support.message.deleted') {
                            // Проверяем, что данные свежие (не старше 5 секунд)
                            if (isset($deleteData['timestamp']) && ($now - $deleteData['timestamp']) < 5) {
                                // Удаляем из кеша
                                Yii::$app->cache->delete($deleteCacheKey);
                                
                                $this->log("Processing message delete notification: ticketId={$deleteData['ticketId']}, messageId={$deleteData['messageId']}");
                                
                                // Отправляем уведомление об удалении
                                $this->sendMessageDeleteNotification(
                                    $deleteData['ticketId'],
                                    $deleteData['messageId']
                                );
                                
                                $processedMessageDeletes[] = $queueKeyId;
                            }
                        } else {
                            $this->log("Delete cache data not found or invalid for key: {$deleteCacheKey}");
                        }
                    }
                    
                    // Удаляем обработанные удаления из очереди
                    if (!empty($processedMessageDeletes)) {
                        foreach ($processedMessageDeletes as $queueKeyId) {
                            unset($messageDeletesQueue[$queueKeyId]);
                        }
                        if (empty($messageDeletesQueue)) {
                            Yii::$app->cache->delete($messageDeletesQueueKey);
                        } else {
                            Yii::$app->cache->set($messageDeletesQueueKey, $messageDeletesQueue, 10);
                        }
                    }
                    
                    // Обработка уведомлений о покупках
                    $purchasesQueueKey = 'ws_purchases_queue';
                    $purchasesQueue = Yii::$app->cache->get($purchasesQueueKey);
                    if (!is_array($purchasesQueue)) {
                        $purchasesQueue = [];
                    }
                    
                    $processedPurchases = [];
                    foreach ($purchasesQueue as $userId => $queueTimestamp) {
                        // Проверяем только свежие уведомления (не старше 5 секунд)
                        if (($now - $queueTimestamp) > 5) {
                            continue;
                        }
                        
                        $purchaseCacheKey = 'ws_purchase_' . $userId;
                        $purchaseData = Yii::$app->cache->get($purchaseCacheKey);
                        
                        if ($purchaseData && isset($purchaseData['type']) && $purchaseData['type'] === 'purchase.completed') {
                            // Проверяем, что данные свежие (не старше 5 секунд)
                            if (isset($purchaseData['timestamp']) && ($now - $purchaseData['timestamp']) < 5) {
                                // Удаляем из кеша
                                Yii::$app->cache->delete($purchaseCacheKey);
                                
                                // Отправляем уведомление о покупке
                                $this->sendPurchaseNotification(
                                    $purchaseData['userId'],
                                    $purchaseData['newBalance'] ?? null
                                );
                                
                                $processedPurchases[] = $userId;
                            }
                        }
                    }
                    
                    // Удаляем обработанные покупки из очереди
                    if (!empty($processedPurchases)) {
                        foreach ($processedPurchases as $userId) {
                            unset($purchasesQueue[$userId]);
                        }
                        if (empty($purchasesQueue)) {
                            Yii::$app->cache->delete($purchasesQueueKey);
                        } else {
                            Yii::$app->cache->set($purchasesQueueKey, $purchasesQueue, 10);
                        }
                    }
                    
                    // Обработка уведомлений о блокировках пользователей
                    $userBlocksQueueKey = 'ws_user_blocks_queue';
                    $userBlocksQueue = Yii::$app->cache->get($userBlocksQueueKey);
                    if (!is_array($userBlocksQueue)) {
                        $userBlocksQueue = [];
                    }
                    
                    $processedUserBlocks = [];
                    foreach ($userBlocksQueue as $queueKeyId => $queueTimestamp) {
                        // Проверяем только свежие уведомления (не старше 5 секунд)
                        if (($now - $queueTimestamp) > 5) {
                            continue;
                        }
                        
                        // queueKeyId имеет формат "userId_blockType"
                        $parts = explode('_', $queueKeyId, 2);
                        if (count($parts) !== 2) {
                            $this->log("Invalid queueKeyId format for user block: {$queueKeyId}");
                            continue;
                        }
                        list($userId, $blockType) = $parts;
                        $blockCacheKey = 'ws_user_block_' . $userId . '_' . $blockType;
                        $blockData = Yii::$app->cache->get($blockCacheKey);
                        
                        if ($blockData && isset($blockData['type']) && $blockData['type'] === 'support.user.blocked') {
                            // Проверяем, что данные свежие (не старше 5 секунд)
                            if (isset($blockData['timestamp']) && ($now - $blockData['timestamp']) < 5) {
                                // Удаляем из кеша
                                Yii::$app->cache->delete($blockCacheKey);
                                
                                // Отправляем уведомление о блокировке
                                $this->sendUserBlockedNotification(
                                    $blockData['userId'],
                                    $blockData['blockType'],
                                    $blockData['blocked'],
                                    $blockData['blockedAt'] ?? null
                                );
                                
                                $processedUserBlocks[] = $queueKeyId;
                            }
                        }
                    }
                    
                    // Удаляем обработанные блокировки из очереди
                    if (!empty($processedUserBlocks)) {
                        foreach ($processedUserBlocks as $queueKeyId) {
                            unset($userBlocksQueue[$queueKeyId]);
                        }
                        if (empty($userBlocksQueue)) {
                            Yii::$app->cache->delete($userBlocksQueueKey);
                        } else {
                            Yii::$app->cache->set($userBlocksQueueKey, $userBlocksQueue, 10);
                        }
                    }
                } catch (\Exception $e) {
                    $this->log("Error processing cached support messages: " . $e->getMessage());
                }
            });

            // Проверка авторизации каждые 5 секунд
            $loop->addPeriodicTimer(5, function () {
                $now = time();
                foreach ($this->clients as $client) {
                    // Закрываем соединение, если клиент не авторизован в течение таймаута
                    if (!$client->authenticated && isset($client->connectedAt)) {
                        $timeSinceConnect = $now - $client->connectedAt;
                        if ($timeSinceConnect >= $this->authTimeout) {
                            $this->log("Closing unauthenticated client: " . $client->remoteAddress . " (timeout: {$timeSinceConnect}s)");
                            try {
                                $client->close(1008, 'Authentication timeout');
                            } catch (\Throwable $e) {
                                $this->log("Error closing client: " . $e->getMessage());
                            }
                            continue;
                        }
                    }

                    // Idle-таймаут убран - WebSocket соединение само поддерживается браузером
                }
            });
        });
    }

    /**
     * Получить команду из сообщения
     */
    protected function getCommand(ConnectionInterface $from, $msg)
    {
        $request = json_decode($msg, true);
        if (!is_array($request)) {
            return null;
        }

        $action = !empty($request['action']) ? $request['action'] : parent::getCommand($from, $msg);
        
        // Логируем команды
        if ($action) {
            $userId = !empty($from->user) ? $from->user->id : 'anonymous';
            $this->log("Command from user {$userId}: {$action}");
            // Дополнительное логирование для syncTicket
            if ($action === 'syncTicket') {
                $this->log("SyncTicket command detected, ticketId: " . (!empty($request['ticketId']) ? $request['ticketId'] : 'missing'));
            }
        }

        return $action;
    }

    /**
     * Авторизация через JWT токен
     */
    public function commandAuth(ConnectionInterface $client, $msg)
    {
        try {
            $request = json_decode($msg, true);
            $result = [];

            // Валидация входных данных
            if (empty($request['token'])) {
                $result['success'] = false;
                $result['message'] = 'Token is required';
                $client->send(json_encode($result));
                $this->log("Auth failed: Token is required from " . $client->remoteAddress);
                // Закрываем соединение при отсутствии токена
                try {
                    $client->close(1008, 'No token provided');
                } catch (\Throwable $e) {
                    $this->log("Error closing client: " . $e->getMessage());
                }
                return;
            }

            try {
                // Валидируем JWT токен
                $payload = $this->jwtService->validateToken($request['token']);
                
                // Получаем пользователя из БД
                $user = User::findOne($payload['user_id']);
                
                if (!$user) {
                    $result['success'] = false;
                    $result['message'] = 'User not found';
                    $client->send(json_encode($result));
                    $this->log("Auth failed: User not found (user_id: {$payload['user_id']})");
                    // Закрываем соединение при невалидном пользователе
                    try {
                        $client->close(1008, 'Invalid user');
                    } catch (\Throwable $e) {
                        $this->log("Error closing client: " . $e->getMessage());
                    }
                    return;
                }

                // Проверяем, что steam_id совпадает
                if ($user->steam_id !== $payload['steam_id']) {
                    $result['success'] = false;
                    $result['message'] = 'Invalid token';
                    $client->send(json_encode($result));
                    $this->log("Auth failed: Invalid steam_id for user {$user->id}");
                    // Закрываем соединение при несовпадении steam_id
                    try {
                        $client->close(1008, 'Invalid token');
                    } catch (\Throwable $e) {
                        $this->log("Error closing client: " . $e->getMessage());
                    }
                    return;
                }

                // Авторизация успешна
                $client->authenticated = true;
                $client->user = $user;
                
                // Проверяем роли пользователя
                $client->isAdmin = $user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT]);
                
                $this->indexClientByUserId($client);
                
                $result['success'] = true;
                $result['message'] = 'Authenticated successfully';
                $client->send(json_encode($result));
                $this->log("User {$user->id} authenticated from " . $client->remoteAddress . " (isAdmin: " . ($client->isAdmin ? 'true' : 'false') . ")");
            } catch (\Exception $e) {
                $result['success'] = false;
                $result['message'] = 'Authentication failed: ' . $e->getMessage();
                $client->send(json_encode($result));
                $this->log("Auth failed: " . $e->getMessage());
                // Закрываем соединение при ошибке валидации токена
                try {
                    $client->close(1008, 'Token validation failed');
                } catch (\Throwable $closeException) {
                    $this->log("Error closing client: " . $closeException->getMessage());
                }
            }
        } catch (\Exception $e) {
            $this->log("Error in commandAuth: " . $e->getMessage());
            try {
                $client->close(1011, 'Server error');
            } catch (\Throwable $closeException) {
                $this->log("Error closing client: " . $closeException->getMessage());
            }
        }
    }

    /**
     * Подписка на тикет
     */
    public function commandSubscribeTicket(ConnectionInterface $client, $msg)
    {
        // Проверяем авторизацию
        if (empty($client->authenticated)) {
            $this->log("SubscribeTicket rejected: client not authenticated from " . $client->remoteAddress);
            try {
                $client->close(1008, 'Not authenticated');
            } catch (\Throwable $e) {
                $this->log("Error closing client: " . $e->getMessage());
            }
            return;
        }

        try {
            $request = json_decode($msg, true);
            if (empty($request['ticketId'])) {
                $this->log("SubscribeTicket failed: ticketId is required");
                return;
            }

            $ticketId = (int)$request['ticketId'];
            
            // Получаем текущий массив подписок
            $subscribedTickets = isset($client->subscribedTickets) ? (array)$client->subscribedTickets : [];
            
            if (!in_array($ticketId, $subscribedTickets)) {
                $subscribedTickets[] = $ticketId;
                $client->subscribedTickets = $subscribedTickets;
            }

            // Индексируем клиента по ticket_id
            if (!isset($this->clientsByTicketId[$ticketId])) {
                $this->clientsByTicketId[$ticketId] = [];
            }
            if (!in_array($client, $this->clientsByTicketId[$ticketId], true)) {
                $this->clientsByTicketId[$ticketId][] = $client;
            }

            $userId = !empty($client->user) ? $client->user->id : 'unknown';
            $this->log("User {$userId} subscribed to ticket {$ticketId}");
        } catch (\Exception $e) {
            $this->log("Error in commandSubscribeTicket: " . $e->getMessage());
        }
    }

    /**
     * Отписка от тикета
     */
    public function commandUnsubscribeTicket(ConnectionInterface $client, $msg)
    {
        // Проверяем авторизацию
        if (empty($client->authenticated)) {
            return;
        }

        try {
            $request = json_decode($msg, true);
            if (empty($request['ticketId'])) {
                return;
            }

            $ticketId = (int)$request['ticketId'];
            
            // Получаем текущий массив подписок
            $subscribedTickets = isset($client->subscribedTickets) ? (array)$client->subscribedTickets : [];
            
            $key = array_search($ticketId, $subscribedTickets);
            if ($key !== false) {
                unset($subscribedTickets[$key]);
                $client->subscribedTickets = array_values($subscribedTickets);
            }

            // Удаляем из индекса по ticket_id
            if (isset($this->clientsByTicketId[$ticketId])) {
                $key = array_search($client, $this->clientsByTicketId[$ticketId], true);
                if ($key !== false) {
                    unset($this->clientsByTicketId[$ticketId][$key]);
                    if (empty($this->clientsByTicketId[$ticketId])) {
                        unset($this->clientsByTicketId[$ticketId]);
                    }
                }
            }

            $userId = !empty($client->user) ? $client->user->id : 'unknown';
            $this->log("User {$userId} unsubscribed from ticket {$ticketId}");
        } catch (\Exception $e) {
            $this->log("Error in commandUnsubscribeTicket: " . $e->getMessage());
        }
    }

    /**
     * Синхронизация тикета (проверка количества сообщений)
     */
    public function commandSyncTicket(ConnectionInterface $client, $msg)
    {
        $this->log("commandSyncTicket called with message: " . substr($msg, 0, 200));
        
        // Проверяем авторизацию
        if (empty($client->authenticated)) {
            $this->log("SyncTicket rejected: client not authenticated from " . $client->remoteAddress);
            return;
        }

        try {
            $request = json_decode($msg, true);
            $this->log("SyncTicket request decoded: " . json_encode($request));
            
            if (empty($request['ticketId'])) {
                $this->log("SyncTicket failed: ticketId is required");
                return;
            }

            $ticketNumber = (int)$request['ticketId'];
            
            // Получаем тикет по номеру
            $ticket = \common\models\support\Support::findByNumber($ticketNumber);
            if (!$ticket) {
                $result = [
                    'type' => 'support.sync.response',
                    'ticketId' => $ticketNumber,
                    'success' => false,
                    'message' => 'Ticket not found',
                ];
                $client->send(json_encode($result));
                return;
            }

            // Получаем количество сообщений в тикете
            $messageCount = \common\models\support\SupportMessage::find()
                ->where(['support_id' => $ticket->id])
                ->count();

            // Отправляем ответ клиенту
            $result = [
                'type' => 'support.sync.response',
                'ticketId' => $ticketNumber,
                'success' => true,
                'messageCount' => (int)$messageCount,
            ];
            $client->send(json_encode($result));
            
            $userId = !empty($client->user) ? $client->user->id : 'unknown';
            $this->log("SyncTicket response sent to user {$userId} for ticket {$ticketNumber}: messageCount={$messageCount}");
        } catch (\Exception $e) {
            $this->log("Error in commandSyncTicket: " . $e->getMessage());
        }
    }

    /**
     * Отправка typing индикатора
     */
    public function commandTyping(ConnectionInterface $client, $msg)
    {
        // Проверяем авторизацию
        if (empty($client->authenticated)) {
            return;
        }

        try {
            $request = json_decode($msg, true);
            if (empty($request['ticketId']) || !isset($request['typing'])) {
                return;
            }

            $ticketId = (int)$request['ticketId'];
            $typing = (bool)$request['typing'];
            $userId = !empty($client->user) ? $client->user->id : null;

            if (!$userId) {
                return;
            }

            // Отправляем typing индикатор всем подписчикам тикета
            $clients = $this->getClientsByTicketId($ticketId);
            $message = [
                'type' => 'support.typing',
                'ticketId' => $ticketId,
                'userId' => $userId,
                'typing' => $typing,
            ];

            foreach ($clients as $subscriber) {
                if ($subscriber !== $client) {
                    try {
                        $subscriber->send(json_encode($message));
                    } catch (\Exception $ex) {
                        $this->log("Error broadcasting typing: " . $ex->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            $this->log("Error in commandTyping: " . $e->getMessage());
        }
    }

    /**
     * Обработка ping (игнорируем, так как ping/pong не используется)
     */
    public function commandPing(ConnectionInterface $client, $msg)
    {
        // Игнорируем ping сообщения - они не нужны
        return;
    }

    /**
     * Обработка pong (игнорируем, так как ping/pong не используется)
     */
    public function commandPong(ConnectionInterface $client, $msg)
    {
        // Игнорируем pong сообщения - они не нужны
        return;
    }

    /**
     * Получить клиентов по user_id
     */
    private function getClientsByUserId($userId)
    {
        return isset($this->clientsByUserId[$userId]) ? $this->clientsByUserId[$userId] : [];
    }

    /**
     * Получить клиентов по ticket_id
     */
    private function getClientsByTicketId($ticketId)
    {
        return isset($this->clientsByTicketId[$ticketId]) ? $this->clientsByTicketId[$ticketId] : [];
    }

    /**
     * Получить всех админов/модераторов/поддержку
     */
    private function getAdminClients()
    {
        $adminClients = [];
        $totalClients = count($this->clients);
        $this->log("getAdminClients: Total clients: {$totalClients}");
        
        foreach ($this->clients as $client) {
            $isAuthenticated = !empty($client->authenticated);
            $isAdmin = isset($client->isAdmin) && $client->isAdmin === true;
            $userId = !empty($client->user) ? $client->user->id : 'anonymous';
            
            $this->log("getAdminClients: Client user_id={$userId}, authenticated={$isAuthenticated}, isAdmin={$isAdmin}");
            
            if ($isAuthenticated && $isAdmin) {
                $adminClients[] = $client;
                $this->log("getAdminClients: Added admin client user_id={$userId}");
            }
        }
        
        $this->log("getAdminClients: Found " . count($adminClients) . " admin clients");
        return $adminClients;
    }

    /**
     * Отправка нового сообщения поддержки
     */
    private function broadcastNewMessage($ticketId, $messageId, $userId, $ownerUserId = null)
    {
        $message = [
            'type' => 'support.message',
            'ticketId' => $ticketId,
            'messageId' => $messageId,
            'userId' => $userId,
        ];

        // Собираем всех получателей
        $recipients = [];
        
        // 1. Все админы/модераторы/поддержка получают все сообщения
        $adminClients = $this->getAdminClients();
        $this->log("Admin clients count: " . count($adminClients));
        foreach ($adminClients as $client) {
            $recipients[] = $client;
        }
        
        // 2. Владелец тикета получает сообщения в своем тикете
        if ($ownerUserId) {
            $ownerClients = $this->getClientsByUserId($ownerUserId);
            $this->log("Owner clients count for user {$ownerUserId}: " . count($ownerClients));
            foreach ($ownerClients as $client) {
                if (!in_array($client, $recipients, true)) {
                    $recipients[] = $client;
                }
            }
        }
        
        // 3. Все подписчики тикета также получают сообщения
        $subscribedClients = $this->getClientsByTicketId($ticketId);
        $this->log("Subscribed clients count for ticket {$ticketId}: " . count($subscribedClients));
        foreach ($subscribedClients as $client) {
            if (!in_array($client, $recipients, true)) {
                $recipients[] = $client;
            }
        }

        $this->log("Total recipients for message: " . count($recipients));

        // Отправляем сообщение всем получателям
        $sentCount = 0;
        foreach ($recipients as $client) {
            try {
                $client->send(json_encode($message));
                $sentCount++;
            } catch (\Exception $ex) {
                $this->log("Error broadcasting message: " . $ex->getMessage());
            }
        }
        
        $this->log("Messages sent: {$sentCount}/" . count($recipients));
    }

    /**
     * Статический метод для отправки нового сообщения поддержки
     * Использует кеш для передачи данных между процессами (как в ChatServer)
     */
    public static function broadcastNewSupportMessage($ticketId, $messageId, $userId, $ownerUserId = null)
    {
        try {
            // Записываем в кеш (WebSocket сервер будет читать из кеша)
            // Используем формат: ws_support_message_{ticketId}
            $cacheKey = 'ws_support_message_' . $ticketId;
            $data = [
                'type' => 'support.message',
                'ticketId' => $ticketId,
                'messageId' => $messageId,
                'userId' => $userId,
                'ownerUserId' => $ownerUserId,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 10);
            
            // Также добавляем в глобальный список тикетов с новыми сообщениями
            $queueKey = 'ws_support_messages_queue';
            $queue = Yii::$app->cache->get($queueKey);
            if (!is_array($queue)) {
                $queue = [];
            }
            $queue[$ticketId] = time();
            // Оставляем только последние 1000 тикетов
            if (count($queue) > 1000) {
                arsort($queue);
                $queue = array_slice($queue, 0, 1000, true);
            }
            Yii::$app->cache->set($queueKey, $queue, 10);

            FrontendPushGatewayServer::queueSupportMessage(
                (int) $ticketId,
                (int) $messageId,
                (int) $userId,
                $ownerUserId !== null ? (int) $ownerUserId : null
            );

            return true;
        } catch (\Exception $ex) {
            error_log("NotificationServer::broadcastNewSupportMessage failed: " . $ex->getMessage());
            return false;
        }
    }

    /**
     * Отправка обновления статуса тикета
     */
    private function broadcastTicketStatusUpdate($ticketId, $status)
    {
        $message = [
            'type' => 'support.status',
            'ticketId' => $ticketId,
            'status' => $status,
        ];

        // Получаем тикет для определения владельца
        $ticket = \common\models\support\Support::findByNumber($ticketId);
        $ownerUserId = $ticket ? $ticket->user_id : null;

        // Собираем всех получателей
        $recipients = [];
        
        // 1. Все админы/модераторы/поддержка получают обновления статуса
        $adminClients = $this->getAdminClients();
        foreach ($adminClients as $client) {
            $recipients[] = $client;
        }
        
        // 2. Владелец тикета получает обновления статуса своего тикета
        if ($ownerUserId) {
            $ownerClients = $this->getClientsByUserId($ownerUserId);
            foreach ($ownerClients as $client) {
                if (!in_array($client, $recipients, true)) {
                    $recipients[] = $client;
                }
            }
        }
        
        // 3. Все подписчики тикета также получают обновления статуса
        $subscribedClients = $this->getClientsByTicketId($ticketId);
        foreach ($subscribedClients as $client) {
            if (!in_array($client, $recipients, true)) {
                $recipients[] = $client;
            }
        }

        // Отправляем сообщение всем получателям
        $sentCount = 0;
        foreach ($recipients as $client) {
            try {
                $client->send(json_encode($message));
                $sentCount++;
            } catch (\Exception $ex) {
                $this->log("Error broadcasting status: " . $ex->getMessage());
            }
        }
        
        $this->log("Status updates sent: {$sentCount}/" . count($recipients) . " for ticket {$ticketId}");
    }

    /**
     * Статический метод для отправки обновления статуса тикета
     * Использует кеш для передачи данных между процессами
     */
    public static function broadcastTicketStatus($ticketId, $status)
    {
        try {
            $cacheKey = 'ws_support_status_' . $ticketId;
            $data = [
                'type' => 'support.status',
                'ticketId' => $ticketId,
                'status' => $status,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 10);

            $queueKey = 'ws_support_status_queue';
            $queue = Yii::$app->cache->get($queueKey);
            if (!is_array($queue)) {
                $queue = [];
            }
            $queue[$ticketId] = time();
            if (count($queue) > 1000) {
                arsort($queue);
                $queue = array_slice($queue, 0, 1000, true);
            }
            Yii::$app->cache->set($queueKey, $queue, 10);

            FrontendPushGatewayServer::queueTicketStatus((int) $ticketId, (string) $status);

            return true;
        } catch (\Exception $ex) {
            error_log("NotificationServer::broadcastTicketStatus failed: " . $ex->getMessage());
            return false;
        }
    }

    /**
     * Отправка уведомления о покупке товара
     */
    private function sendPurchaseNotification($userId, $newBalance)
    {
        $message = [
            'type' => 'purchase.completed',
            'newBalance' => $newBalance,
        ];

        // Отправляем уведомление только пользователю, который купил товар
        $userClients = $this->getClientsByUserId($userId);
        
        $sentCount = 0;
        foreach ($userClients as $client) {
            try {
                $client->send(json_encode($message));
                $sentCount++;
            } catch (\Exception $ex) {
                $this->log("Error broadcasting purchase notification: " . $ex->getMessage());
            }
        }
        
        $this->log("Purchase notifications sent: {$sentCount}/" . count($userClients) . " for user {$userId}");
    }

    /**
     * Статический метод для отправки уведомления о создании нового тикета
     * Использует кеш для передачи данных между процессами
     */
    public static function broadcastNewTicket($ticketId, $userId)
    {
        try {
            $cacheKey = 'ws_support_new_ticket_' . $ticketId;
            $data = [
                'type' => 'support.new_ticket',
                'ticketId' => $ticketId,
                'userId' => $userId,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 10);

            $queueKey = 'ws_support_new_tickets_queue';
            $queue = Yii::$app->cache->get($queueKey);
            if (!is_array($queue)) {
                $queue = [];
            }
            $queue[$ticketId] = time();
            if (count($queue) > 1000) {
                arsort($queue);
                $queue = array_slice($queue, 0, 1000, true);
            }
            Yii::$app->cache->set($queueKey, $queue, 10);

            FrontendPushGatewayServer::queueNewTicket((int) $ticketId, (int) $userId);

            return true;
        } catch (\Exception $ex) {
            error_log("NotificationServer::broadcastNewTicket failed: " . $ex->getMessage());
            return false;
        }
    }

    /**
     * Статический метод для отправки уведомления о покупке товара
     * Использует кеш для передачи данных между процессами
     */
    public static function broadcastPurchaseNotification($userId, $newBalance)
    {
        try {
            $cacheKey = 'ws_purchase_' . $userId;
            $data = [
                'type' => 'purchase.completed',
                'userId' => $userId,
                'newBalance' => $newBalance,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 10);

            $queueKey = 'ws_purchases_queue';
            $queue = Yii::$app->cache->get($queueKey);
            if (!is_array($queue)) {
                $queue = [];
            }
            $queue[$userId] = time();
            if (count($queue) > 1000) {
                arsort($queue);
                $queue = array_slice($queue, 0, 1000, true);
            }
            Yii::$app->cache->set($queueKey, $queue, 10);

            FrontendPushGatewayServer::queuePurchase((int) $userId, $newBalance);

            return true;
        } catch (\Exception $ex) {
            error_log("NotificationServer::broadcastPurchaseNotification failed: " . $ex->getMessage());
            return false;
        }
    }

    /**
     * Статический метод для отправки уведомления о блокировке пользователя
     * Использует кеш для передачи данных между процессами
     */
    public static function broadcastUserBlocked($userId, $blockType, $blocked, $blockedAt)
    {
        try {
            $cacheKey = 'ws_user_block_' . $userId . '_' . $blockType;
            $data = [
                'type' => 'support.user.blocked',
                'userId' => $userId,
                'blockType' => $blockType, // 'mute', 'chat', 'account'
                'blocked' => $blocked,
                'blockedAt' => $blockedAt,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 10);

            $queueKey = 'ws_user_blocks_queue';
            $queue = Yii::$app->cache->get($queueKey);
            if (!is_array($queue)) {
                $queue = [];
            }
            $queueKeyId = $userId . '_' . $blockType;
            $queue[$queueKeyId] = time();
            if (count($queue) > 1000) {
                arsort($queue);
                $queue = array_slice($queue, 0, 1000, true);
            }
            Yii::$app->cache->set($queueKey, $queue, 10);

            FrontendPushGatewayServer::queueUserBlocked((int) $userId, (string) $blockType, $blocked, $blockedAt);

            return true;
        } catch (\Exception $ex) {
            error_log("NotificationServer::broadcastUserBlocked failed: " . $ex->getMessage());
            return false;
        }
    }

    /**
     * Отправка уведомления о блокировке пользователя
     */
    private function sendUserBlockedNotification($userId, $blockType, $blocked, $blockedAt)
    {
        $message = [
            'type' => 'support.user.blocked',
            'userId' => $userId,
            'blockType' => $blockType,
            'blocked' => $blocked,
            'blockedAt' => $blockedAt,
        ];

        // Отправляем уведомление всем клиентам пользователя
        $userClients = $this->getClientsByUserId($userId);
        $sentCount = 0;
        foreach ($userClients as $client) {
            try {
                $client->send(json_encode($message));
                $sentCount++;
            } catch (\Exception $ex) {
                $this->log("Failed to send user blocked notification to client: " . $ex->getMessage());
            }
        }
        
        $this->log("User blocked notifications sent: {$sentCount}/" . count($userClients) . " for user {$userId}, type {$blockType}");
    }

    /**
     * Отправка уведомления об обновлении сообщения
     */
    private function sendMessageUpdateNotification($ticketId, $messageId)
    {
        $message = [
            'type' => 'support.message.updated',
            'ticketId' => $ticketId,
            'messageId' => $messageId,
        ];

        // Получаем тикет для определения владельца
        $ticket = \common\models\support\Support::findByNumber($ticketId);
        $ownerUserId = $ticket ? $ticket->user_id : null;

        // Собираем всех получателей (админы и владелец тикета)
        $recipients = [];
        
        // 1. Все админы/модераторы/поддержка получают обновления
        $adminClients = $this->getAdminClients();
        foreach ($adminClients as $client) {
            $recipients[] = $client;
        }
        
        // 2. Владелец тикета получает обновления
        if ($ownerUserId) {
            $ownerClients = $this->getClientsByUserId($ownerUserId);
            foreach ($ownerClients as $client) {
                if (!in_array($client, $recipients, true)) {
                    $recipients[] = $client;
                }
            }
        }
        
        // 3. Все подписчики тикета также получают обновления
        $subscribedClients = $this->getClientsByTicketId($ticketId);
        foreach ($subscribedClients as $client) {
            if (!in_array($client, $recipients, true)) {
                $recipients[] = $client;
            }
        }

        // Отправляем сообщение всем получателям
        $sentCount = 0;
        foreach ($recipients as $client) {
            try {
                $client->send(json_encode($message));
                $sentCount++;
            } catch (\Exception $ex) {
                $this->log("Error broadcasting message update: " . $ex->getMessage());
            }
        }
        
        $this->log("Message update notifications sent: {$sentCount}/" . count($recipients) . " for ticket {$ticketId}, message {$messageId}");
    }

    /**
     * Отправка уведомления об удалении сообщения
     */
    private function sendMessageDeleteNotification($ticketId, $messageId)
    {
        $message = [
            'type' => 'support.message.deleted',
            'ticketId' => $ticketId,
            'messageId' => $messageId,
        ];

        // Получаем тикет для определения владельца
        $ticket = \common\models\support\Support::findByNumber($ticketId);
        $ownerUserId = $ticket ? $ticket->user_id : null;

        // Собираем всех получателей (админы и владелец тикета)
        $recipients = [];
        
        // 1. Все админы/модераторы/поддержка получают обновления
        $adminClients = $this->getAdminClients();
        foreach ($adminClients as $client) {
            $recipients[] = $client;
        }
        
        // 2. Владелец тикета получает обновления
        if ($ownerUserId) {
            $ownerClients = $this->getClientsByUserId($ownerUserId);
            foreach ($ownerClients as $client) {
                if (!in_array($client, $recipients, true)) {
                    $recipients[] = $client;
                }
            }
        }
        
        // 3. Все подписчики тикета также получают обновления
        $subscribedClients = $this->getClientsByTicketId($ticketId);
        foreach ($subscribedClients as $client) {
            if (!in_array($client, $recipients, true)) {
                $recipients[] = $client;
            }
        }

        // Отправляем сообщение всем получателям
        $sentCount = 0;
        foreach ($recipients as $client) {
            try {
                $client->send(json_encode($message));
                $sentCount++;
            } catch (\Exception $ex) {
                $this->log("Error broadcasting message delete: " . $ex->getMessage());
            }
        }
        
        $this->log("Message delete notifications sent: {$sentCount}/" . count($recipients) . " for ticket {$ticketId}, message {$messageId}");
    }

    /**
     * Статический метод для отправки уведомления об обновлении сообщения
     * Использует кеш для передачи данных между процессами
     */
    public static function broadcastMessageUpdate($ticketId, $messageId)
    {
        try {
            $cacheKey = 'ws_support_message_update_' . $ticketId . '_' . $messageId;
            $data = [
                'type' => 'support.message.updated',
                'ticketId' => $ticketId,
                'messageId' => $messageId,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 10);

            $queueKey = 'ws_support_message_updates_queue';
            $queue = Yii::$app->cache->get($queueKey);
            if (!is_array($queue)) {
                $queue = [];
            }
            $queueKeyId = $ticketId . '_' . $messageId;
            $queue[$queueKeyId] = time();
            if (count($queue) > 1000) {
                arsort($queue);
                $queue = array_slice($queue, 0, 1000, true);
            }
            Yii::$app->cache->set($queueKey, $queue, 10);

            FrontendPushGatewayServer::queueMessageUpdate((int) $ticketId, (int) $messageId);

            return true;
        } catch (\Exception $ex) {
            error_log("NotificationServer::broadcastMessageUpdate failed: " . $ex->getMessage());
            return false;
        }
    }

    /**
     * Статический метод для отправки уведомления об удалении сообщения
     * Использует кеш для передачи данных между процессами
     */
    public static function broadcastMessageDelete($ticketId, $messageId)
    {
        try {
            $cacheKey = 'ws_support_message_delete_' . $ticketId . '_' . $messageId;
            $data = [
                'type' => 'support.message.deleted',
                'ticketId' => $ticketId,
                'messageId' => $messageId,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 10);

            $queueKey = 'ws_support_message_deletes_queue';
            $queue = Yii::$app->cache->get($queueKey);
            if (!is_array($queue)) {
                $queue = [];
            }
            $queueKeyId = $ticketId . '_' . $messageId;
            $queue[$queueKeyId] = time();
            if (count($queue) > 1000) {
                arsort($queue);
                $queue = array_slice($queue, 0, 1000, true);
            }
            Yii::$app->cache->set($queueKey, $queue, 10);

            FrontendPushGatewayServer::queueMessageDelete((int) $ticketId, (int) $messageId);

            return true;
        } catch (\Exception $ex) {
            error_log("NotificationServer::broadcastMessageDelete failed: " . $ex->getMessage());
            return false;
        }
    }

    /**
     * Логирование
     */
    private function log($message)
    {
        echo date('Y-m-d H:i:s') . " [NotificationWS] {$message}" . PHP_EOL;
    }
}
