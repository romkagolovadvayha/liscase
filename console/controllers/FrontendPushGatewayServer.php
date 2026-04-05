<?php

namespace console\controllers;

use common\components\helpers\Role;
use common\models\support\Support;
use common\models\user\User;
use consik\yii2websocket\events\WSClientEvent;
use consik\yii2websocket\WebSocketServer;
use Ratchet\ConnectionInterface;
use Yii;
use api\components\jwt\JwtService;

/**
 * WebSocket-шлюз push-уведомлений для нового фронта (Next.js).
 *
 * Протокол:
 * - Клиент → сервер: JSON с полем "action": auth | subscribeTicket | unsubscribeTicket | typing | ping
 * - Сервер → клиент: единый конверт { v, channel, event, payload, ts }
 *
 * События между PHP (API) и этим процессом передаются через кеш (префикс fp_ws_*), см. static queue*().
 */
class FrontendPushGatewayServer extends WebSocketServer
{
    public const PROTOCOL_VERSION = 1;

    public const CHANNEL_NOTIFICATION = 'notification';
    public const CHANNEL_SYSTEM = 'system';

    /** События приложения (расширяйте по мере необходимости) */
    public const EVENT_SUPPORT_MESSAGE_NEW = 'support.message.new';
    public const EVENT_SUPPORT_TICKET_STATUS = 'support.ticket.status';
    public const EVENT_SUPPORT_TICKET_NEW = 'support.ticket.new';
    public const EVENT_SUPPORT_MESSAGE_UPDATED = 'support.message.updated';
    public const EVENT_SUPPORT_MESSAGE_DELETED = 'support.message.deleted';
    /** Прочтение сообщений собеседником — обновление галочек у отправителя */
    public const EVENT_SUPPORT_MESSAGES_READ = 'support.messages.read';
    public const EVENT_SUPPORT_USER_BLOCKED = 'support.user.blocked';
    public const EVENT_BALANCE_UPDATED = 'balance.updated';
    /** Корзина /store: нужно перезагрузить список (покупка, награда и т.п.) */
    public const EVENT_STORE_INVENTORY_CHANGED = 'store.inventory.changed';

    public const EVENT_AUTH_OK = 'auth.ok';
    public const EVENT_PONG = 'pong';

    /** @var self|null */
    private static $instance = null;

    /** @var JwtService */
    private $jwtService;

    /** @var array<int, ConnectionInterface[]> */
    private $clientsByUserId = [];

    /** @var array<int, ConnectionInterface[]> ticket number => connections */
    private $clientsByTicketId = [];

    public $authTimeout = 15;

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    public function init()
    {
        parent::init();
        self::$instance = $this;

        $this->jwtService = new JwtService([
            'secret' => Yii::$app->params['jwt']['secret'] ?? getenv('JWT_SECRET'),
            'algorithm' => Yii::$app->params['jwt']['algorithm'] ?? 'HS256',
            'expiration' => Yii::$app->params['jwt']['expiration'] ?? 3600,
            'refreshExpiration' => Yii::$app->params['jwt']['refreshExpiration'] ?? 604800,
        ]);
        $this->jwtService->init();

        $this->on(self::EVENT_CLIENT_CONNECTED, function (WSClientEvent $e) {
            $client = $e->client;
            $client->user = null;
            $client->authenticated = false;
            $client->connectedAt = time();
            if (!isset($client->subscribedTickets)) {
                $client->subscribedTickets = [];
            }
        });

        $this->on(self::EVENT_CLIENT_DISCONNECTED, function (WSClientEvent $e) {
            $this->removeClientFromIndexes($e->client);
        });

        $this->on(self::EVENT_WEBSOCKET_OPEN, function () {
            /** @var \Ratchet\Server\IoServer $io */
            $io = $this->server;
            $loop = $io->loop;

            $loop->addPeriodicTimer(1, function () {
                try {
                    $this->processCacheQueues();
                } catch (\Throwable $e) {
                    $this->log('processCacheQueues: ' . $e->getMessage());
                }
            });

            $loop->addPeriodicTimer(5, function () {
                $now = time();
                foreach ($this->clients as $client) {
                    if (empty($client->authenticated) && isset($client->connectedAt)) {
                        if ($now - $client->connectedAt >= $this->authTimeout) {
                            try {
                                $client->close(1008, 'Authentication timeout');
                            } catch (\Throwable $e) {
                            }
                        }
                    }
                }
            });
        });
    }

    protected function getCommand(ConnectionInterface $from, $msg)
    {
        $request = json_decode($msg, true);
        if (!is_array($request)) {
            return null;
        }
        return !empty($request['action']) ? $request['action'] : parent::getCommand($from, $msg);
    }

    public function commandAuth(ConnectionInterface $client, $msg)
    {
        try {
            $request = json_decode($msg, true);
            $result = ['v' => self::PROTOCOL_VERSION, 'channel' => self::CHANNEL_SYSTEM];

            if (empty($request['token'])) {
                $result['event'] = 'auth.error';
                $result['payload'] = ['message' => 'Token is required'];
                $result['ts'] = time();
                $client->send(json_encode($result));
                $client->close(1008, 'No token provided');
                return;
            }

            try {
                $payload = $this->jwtService->validateToken($request['token']);
                $user = User::findOne($payload['user_id']);
                if (!$user) {
                    $result['event'] = 'auth.error';
                    $result['payload'] = ['message' => 'User not found'];
                    $result['ts'] = time();
                    $client->send(json_encode($result));
                    $client->close(1008, 'Invalid user');
                    return;
                }
                if ($user->steam_id !== $payload['steam_id']) {
                    $result['event'] = 'auth.error';
                    $result['payload'] = ['message' => 'Invalid token'];
                    $result['ts'] = time();
                    $client->send(json_encode($result));
                    $client->close(1008, 'Invalid token');
                    return;
                }

                $client->authenticated = true;
                $client->user = $user;
                $client->isAdmin = $user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT]);
                $this->indexClientByUserId($client);

                $this->sendEnvelope($client, self::CHANNEL_SYSTEM, self::EVENT_AUTH_OK, [
                    'userId' => $user->id,
                    'isStaff' => (bool) $client->isAdmin,
                ]);
            } catch (\Exception $e) {
                $result['event'] = 'auth.error';
                $result['payload'] = ['message' => $e->getMessage()];
                $result['ts'] = time();
                $client->send(json_encode($result));
                $client->close(1008, 'Token validation failed');
            }
        } catch (\Exception $e) {
            $this->log('commandAuth: ' . $e->getMessage());
            try {
                $client->close(1011, 'Server error');
            } catch (\Throwable $e2) {
            }
        }
    }

    public function commandSubscribeTicket(ConnectionInterface $client, $msg)
    {
        if (empty($client->authenticated)) {
            try {
                $client->close(1008, 'Not authenticated');
            } catch (\Throwable $e) {
            }
            return;
        }
        try {
            $request = json_decode($msg, true);
            if (empty($request['ticketId'])) {
                return;
            }
            $ticketNumber = (int) $request['ticketId'];
            $user = $client->user ?? null;
            if (!$user instanceof User) {
                return;
            }
            $uid = (int) $user->id;
            $isStaff = $user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT]);
            // Сотрудники подписываются по RBAC без поиска строки в БД (консоль/WS может смотреть в другой БД или кэш).
            if (!$isStaff) {
                $ticket = $this->resolveSupportTicketByAnyNumber($ticketNumber);
                if (!$ticket) {
                    $expectedPk = Support::primaryKeyFromPublicNumber($ticketNumber);
                    $pkHint = $expectedPk !== null ? (string) $expectedPk : 'n/a';
                    $dbCtx = $this->getDbConnectionDebugInfo();
                    $this->log("SubscribeTicket denied user {$uid} publicNumber {$ticketNumber} expectedPk {$pkHint} (ticket not found in DB; {$dbCtx})");
                    $this->sendEnvelope($client, self::CHANNEL_SYSTEM, 'subscribe.denied', [
                        'ticketNumber' => $ticketNumber,
                        'reason' => 'ticket_not_found',
                        'expectedSupportPk' => $expectedPk,
                    ]);
                    return;
                }
                if ((int) $ticket->user_id !== $uid) {
                    $owner = (int) $ticket->user_id;
                    $this->log("SubscribeTicket denied user {$uid} ticket {$ticketNumber} owner {$owner} (not owner or staff)");
                    $this->sendEnvelope($client, self::CHANNEL_SYSTEM, 'subscribe.denied', [
                        'ticketNumber' => $ticketNumber,
                        'reason' => 'not_owner',
                        'ownerUserId' => $owner,
                    ]);
                    return;
                }
            }
            $subscribedTickets = isset($client->subscribedTickets) ? (array) $client->subscribedTickets : [];
            if (!in_array($ticketNumber, $subscribedTickets, true)) {
                $subscribedTickets[] = $ticketNumber;
                $client->subscribedTickets = $subscribedTickets;
            }
            if (!isset($this->clientsByTicketId[$ticketNumber])) {
                $this->clientsByTicketId[$ticketNumber] = [];
            }
            if (!in_array($client, $this->clientsByTicketId[$ticketNumber], true)) {
                $this->clientsByTicketId[$ticketNumber][] = $client;
            }
        } catch (\Exception $e) {
            $this->log('commandSubscribeTicket: ' . $e->getMessage());
        }
    }

    public function commandUnsubscribeTicket(ConnectionInterface $client, $msg)
    {
        if (empty($client->authenticated)) {
            return;
        }
        try {
            $request = json_decode($msg, true);
            if (empty($request['ticketId'])) {
                return;
            }
            $ticketNumber = (int) $request['ticketId'];
            $subscribedTickets = isset($client->subscribedTickets) ? (array) $client->subscribedTickets : [];
            $key = array_search($ticketNumber, $subscribedTickets, true);
            if ($key !== false) {
                unset($subscribedTickets[$key]);
                $client->subscribedTickets = array_values($subscribedTickets);
            }
            if (isset($this->clientsByTicketId[$ticketNumber])) {
                $k = array_search($client, $this->clientsByTicketId[$ticketNumber], true);
                if ($k !== false) {
                    unset($this->clientsByTicketId[$ticketNumber][$k]);
                    if (empty($this->clientsByTicketId[$ticketNumber])) {
                        unset($this->clientsByTicketId[$ticketNumber]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->log('commandUnsubscribeTicket: ' . $e->getMessage());
        }
    }

    public function commandPing(ConnectionInterface $client, $msg)
    {
        $this->sendEnvelope($client, self::CHANNEL_SYSTEM, self::EVENT_PONG, []);
    }

    public function commandTyping(ConnectionInterface $client, $msg)
    {
        if (empty($client->authenticated)) {
            return;
        }
        try {
            $request = json_decode($msg, true);
            if (empty($request['ticketId']) || !isset($request['typing'])) {
                return;
            }
            $ticketId = (int) $request['ticketId'];
            $typing = (bool) $request['typing'];
            $userId = !empty($client->user) ? $client->user->id : null;
            if (!$userId) {
                return;
            }
            $message = [
                'v' => self::PROTOCOL_VERSION,
                'channel' => self::CHANNEL_NOTIFICATION,
                'event' => 'support.typing',
                'payload' => [
                    'ticketNumber' => $ticketId,
                    'userId' => $userId,
                    'typing' => $typing,
                ],
                'ts' => time(),
            ];
            $json = json_encode($message);
            foreach ($this->getClientsByTicketId($ticketId) as $subscriber) {
                if ($subscriber !== $client) {
                    try {
                        $subscriber->send($json);
                    } catch (\Exception $ex) {
                    }
                }
            }
        } catch (\Exception $e) {
            $this->log('commandTyping: ' . $e->getMessage());
        }
    }

    private function indexClientByUserId(ConnectionInterface $client): void
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

    private function removeClientFromIndexes(ConnectionInterface $client): void
    {
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
        $subscribedTickets = isset($client->subscribedTickets) ? (array) $client->subscribedTickets : [];
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

    /** @return ConnectionInterface[] */
    private function getClientsByUserId(int $userId): array
    {
        return $this->clientsByUserId[$userId] ?? [];
    }

    /** @return ConnectionInterface[] */
    private function getClientsByTicketId(int $ticketId): array
    {
        return $this->clientsByTicketId[$ticketId] ?? [];
    }

    /** @return ConnectionInterface[] */
    private function getAdminClients(): array
    {
        $out = [];
        foreach ($this->clients as $client) {
            if (!empty($client->authenticated) && !empty($client->isAdmin)) {
                $out[] = $client;
            }
        }
        return $out;
    }

    /**
     * Только публичный номер из API (getNumber / number), см. {@see Support::findByNumber()}.
     */
    private function resolveSupportTicketByAnyNumber(int $publicNumber): ?Support
    {
        return Support::findByNumber($publicNumber);
    }

    /**
     * Диагностика: процесс server-ws должен использовать ту же БД, что и API, иначе findByNumber вернёт null.
     */
    private function getDbConnectionDebugInfo(): string
    {
        try {
            $db = Yii::$app->db;
            $name = $db->createCommand('SELECT DATABASE()')->queryScalar();
            $dsn = (string) ($db->dsn ?? '');
            return 'currentDatabase=' . ($name ?: 'null') . ' dsn=' . $dsn;
        } catch (\Throwable $e) {
            return 'dbError=' . $e->getMessage();
        }
    }

    private function sendEnvelope(ConnectionInterface $client, string $channel, string $event, array $payload): void
    {
        $msg = [
            'v' => self::PROTOCOL_VERSION,
            'channel' => $channel,
            'event' => $event,
            'payload' => $payload,
            'ts' => time(),
        ];
        try {
            $client->send(json_encode($msg));
        } catch (\Exception $e) {
        }
    }

    /**
     * @param ConnectionInterface[] $recipients
     */
    private function sendToMany(array $recipients, string $event, array $payload): void
    {
        foreach ($recipients as $client) {
            $this->sendEnvelope($client, self::CHANNEL_NOTIFICATION, $event, $payload);
        }
    }

    /**
     * Рассылка нового сообщения поддержки (ticketId = номер тикета).
     */
    private function dispatchSupportMessage(int $ticketNumber, int $messageId, int $senderUserId, ?int $ownerUserId): void
    {
        $payload = [
            'ticketNumber' => $ticketNumber,
            'messageId' => $messageId,
            'senderUserId' => $senderUserId,
        ];
        $recipients = [];
        foreach ($this->getAdminClients() as $c) {
            $recipients[] = $c;
        }
        if ($ownerUserId) {
            foreach ($this->getClientsByUserId($ownerUserId) as $c) {
                if (!in_array($c, $recipients, true)) {
                    $recipients[] = $c;
                }
            }
        }
        $this->sendToMany($recipients, self::EVENT_SUPPORT_MESSAGE_NEW, $payload);
    }

    private function dispatchTicketStatus(int $ticketNumber, string $status): void
    {
        $ticket = $this->resolveSupportTicketByAnyNumber($ticketNumber);
        $ownerUserId = $ticket ? (int) $ticket->user_id : null;
        $payload = ['ticketNumber' => $ticketNumber, 'status' => $status];
        $recipients = [];
        foreach ($this->getAdminClients() as $c) {
            $recipients[] = $c;
        }
        if ($ownerUserId) {
            foreach ($this->getClientsByUserId($ownerUserId) as $c) {
                if (!in_array($c, $recipients, true)) {
                    $recipients[] = $c;
                }
            }
        }
        $this->sendToMany($recipients, self::EVENT_SUPPORT_TICKET_STATUS, $payload);
    }

    private function dispatchNewTicket(int $ticketNumber, int $creatorUserId): void
    {
        $payload = ['ticketNumber' => $ticketNumber, 'creatorUserId' => $creatorUserId];
        $this->sendToMany($this->getAdminClients(), self::EVENT_SUPPORT_TICKET_NEW, $payload);
    }

    private function dispatchMessageUpdated(int $ticketNumber, int $messageId): void
    {
        $ticket = $this->resolveSupportTicketByAnyNumber($ticketNumber);
        $ownerUserId = $ticket ? (int) $ticket->user_id : null;
        $payload = ['ticketNumber' => $ticketNumber, 'messageId' => $messageId];
        $recipients = [];
        foreach ($this->getAdminClients() as $c) {
            $recipients[] = $c;
        }
        if ($ownerUserId) {
            foreach ($this->getClientsByUserId($ownerUserId) as $c) {
                if (!in_array($c, $recipients, true)) {
                    $recipients[] = $c;
                }
            }
        }
        $this->sendToMany($recipients, self::EVENT_SUPPORT_MESSAGE_UPDATED, $payload);
    }

    private function dispatchMessageDeleted(int $ticketNumber, int $messageId): void
    {
        $ticket = $this->resolveSupportTicketByAnyNumber($ticketNumber);
        $ownerUserId = $ticket ? (int) $ticket->user_id : null;
        $payload = ['ticketNumber' => $ticketNumber, 'messageId' => $messageId];
        $recipients = [];
        foreach ($this->getAdminClients() as $c) {
            $recipients[] = $c;
        }
        if ($ownerUserId) {
            foreach ($this->getClientsByUserId($ownerUserId) as $c) {
                if (!in_array($c, $recipients, true)) {
                    $recipients[] = $c;
                }
            }
        }
        $this->sendToMany($recipients, self::EVENT_SUPPORT_MESSAGE_DELETED, $payload);
    }

    /**
     * @param array<int, array{messageId: int, senderUserId: int, is_read: bool}> $readStates
     */
    private function dispatchSupportMessagesRead(int $ticketNumber, int $readerUserId, array $readStates): void
    {
        $ticket = $this->resolveSupportTicketByAnyNumber($ticketNumber);
        $ownerUserId = $ticket ? (int) $ticket->user_id : null;
        $payload = [
            'ticketNumber' => $ticketNumber,
            'readerUserId' => $readerUserId,
            'readStates' => $readStates,
        ];
        $recipients = [];
        foreach ($this->getAdminClients() as $c) {
            $recipients[] = $c;
        }
        if ($ownerUserId) {
            foreach ($this->getClientsByUserId($ownerUserId) as $c) {
                if (!in_array($c, $recipients, true)) {
                    $recipients[] = $c;
                }
            }
        }
        $this->sendToMany($recipients, self::EVENT_SUPPORT_MESSAGES_READ, $payload);
    }

    private function dispatchPurchase(int $userId, $newBalance): void
    {
        $this->sendToMany(
            $this->getClientsByUserId($userId),
            self::EVENT_BALANCE_UPDATED,
            ['newBalance' => $newBalance]
        );
    }

    private function dispatchStoreInventoryChanged(int $userId, string $reason = 'purchase'): void
    {
        $this->sendToMany(
            $this->getClientsByUserId($userId),
            self::EVENT_STORE_INVENTORY_CHANGED,
            ['userId' => $userId, 'reason' => $reason]
        );
    }

    /**
     * Доставка на фронт (Next.js) того же обновления баланса, что лаунчер получает через ChatServer.
     * Ключ кеша пишет {@see \common\models\user\UserBalance::recalculateBalance()}.
     */
    private function pushBalanceUpdatesFromUserBalanceCache(): void
    {
        foreach (array_keys($this->clientsByUserId) as $userId) {
            $userId = (int) $userId;
            if ($userId <= 0) {
                continue;
            }
            $balanceKey = 'ws_balance_update_' . $userId;
            $balanceData = Yii::$app->cache->get($balanceKey);
            if (!is_array($balanceData)) {
                continue;
            }
            if (($balanceData['type'] ?? '') !== 'update.balance') {
                continue;
            }
            $ts = isset($balanceData['timestamp']) ? (int) $balanceData['timestamp'] : 0;
            if ($ts === 0 || (time() - $ts) >= 30) {
                continue;
            }
            if (!empty($balanceData['sent'])) {
                continue;
            }
            $clients = $this->getClientsByUserId($userId);
            if ($clients === []) {
                continue;
            }
            $rawBal = $balanceData['balance'] ?? null;
            $payload = [
                'newBalance' => $rawBal !== null ? (float) $rawBal : null,
                'balanceStr' => isset($balanceData['balanceStr']) ? (string) $balanceData['balanceStr'] : null,
            ];
            foreach ($clients as $c) {
                try {
                    $this->sendEnvelope($c, self::CHANNEL_NOTIFICATION, self::EVENT_BALANCE_UPDATED, $payload);
                } catch (\Throwable $e) {
                }
            }
            $balanceData['sent'] = true;
            Yii::$app->cache->set($balanceKey, $balanceData, 5);
        }
    }

    private function dispatchUserBlocked(int $userId, string $blockType, $blocked, $blockedAt): void
    {
        $this->sendToMany(
            $this->getClientsByUserId($userId),
            self::EVENT_SUPPORT_USER_BLOCKED,
            [
                'userId' => $userId,
                'blockType' => $blockType,
                'blocked' => (bool) $blocked,
                'blockedAt' => $blockedAt,
            ]
        );
    }

    /**
     * Обработка очередей из кеша (тот же паттерн, что у NotificationServer, отдельные ключи fp_ws_*).
     */
    private function processCacheQueues(): void
    {
        $now = time();

        // Баланс после пересчёта (UserBalance::recalculateBalance → кеш ws_balance_update_*), как в ChatServer
        $this->pushBalanceUpdatesFromUserBalanceCache();

        $queueKey = 'fp_ws_support_messages_queue';
        $queue = Yii::$app->cache->get($queueKey);
        if (!is_array($queue)) {
            $queue = [];
        }
        $processed = [];
        $msgMaxAge = 60;
        foreach ($queue as $ticketId => $queueTimestamp) {
            $cacheKey = 'fp_ws_support_message_' . $ticketId;
            $cachedData = Yii::$app->cache->get($cacheKey);
            if (!$cachedData || !isset($cachedData['type']) || $cachedData['type'] !== 'support.message') {
                if (($now - (int) $queueTimestamp) > $msgMaxAge) {
                    $processed[] = $ticketId;
                }
                continue;
            }
            $ts = isset($cachedData['timestamp']) ? (int) $cachedData['timestamp'] : (int) $queueTimestamp;
            if (($now - $ts) > $msgMaxAge) {
                Yii::$app->cache->delete($cacheKey);
                $processed[] = $ticketId;
                continue;
            }
            Yii::$app->cache->delete($cacheKey);
            $this->dispatchSupportMessage(
                (int) $cachedData['ticketId'],
                (int) $cachedData['messageId'],
                (int) $cachedData['userId'],
                isset($cachedData['ownerUserId']) ? (int) $cachedData['ownerUserId'] : null
            );
            $processed[] = $ticketId;
        }
        if (!empty($processed)) {
            foreach ($processed as $tid) {
                unset($queue[$tid]);
            }
            if (empty($queue)) {
                Yii::$app->cache->delete($queueKey);
            } else {
                Yii::$app->cache->set($queueKey, $queue, 10);
            }
        }

        $newTicketsQueueKey = 'fp_ws_support_new_tickets_queue';
        $newTicketsQueue = Yii::$app->cache->get($newTicketsQueueKey);
        if (!is_array($newTicketsQueue)) {
            $newTicketsQueue = [];
        }
        $processedNt = [];
        foreach ($newTicketsQueue as $ticketId => $queueTimestamp) {
            if (($now - $queueTimestamp) > 5) {
                continue;
            }
            $nk = 'fp_ws_support_new_ticket_' . $ticketId;
            $data = Yii::$app->cache->get($nk);
            if ($data && isset($data['type']) && $data['type'] === 'support.new_ticket') {
                if (isset($data['timestamp']) && ($now - $data['timestamp']) < 5) {
                    Yii::$app->cache->delete($nk);
                    $this->dispatchNewTicket((int) $data['ticketId'], (int) $data['userId']);
                    $processedNt[] = $ticketId;
                }
            }
        }
        if (!empty($processedNt)) {
            foreach ($processedNt as $tid) {
                unset($newTicketsQueue[$tid]);
            }
            if (empty($newTicketsQueue)) {
                Yii::$app->cache->delete($newTicketsQueueKey);
            } else {
                Yii::$app->cache->set($newTicketsQueueKey, $newTicketsQueue, 10);
            }
        }

        $statusQueueKey = 'fp_ws_support_status_queue';
        $statusQueue = Yii::$app->cache->get($statusQueueKey);
        if (!is_array($statusQueue)) {
            $statusQueue = [];
        }
        $processedSt = [];
        foreach ($statusQueue as $ticketId => $queueTimestamp) {
            if (($now - $queueTimestamp) > 5) {
                continue;
            }
            $sk = 'fp_ws_support_status_' . $ticketId;
            $statusData = Yii::$app->cache->get($sk);
            if ($statusData && isset($statusData['type']) && $statusData['type'] === 'support.status') {
                if (isset($statusData['timestamp']) && ($now - $statusData['timestamp']) < 5) {
                    Yii::$app->cache->delete($sk);
                    $this->dispatchTicketStatus((int) $statusData['ticketId'], (string) $statusData['status']);
                    $processedSt[] = $ticketId;
                }
            }
        }
        if (!empty($processedSt)) {
            foreach ($processedSt as $tid) {
                unset($statusQueue[$tid]);
            }
            if (empty($statusQueue)) {
                Yii::$app->cache->delete($statusQueueKey);
            } else {
                Yii::$app->cache->set($statusQueueKey, $statusQueue, 10);
            }
        }

        $messageUpdatesQueueKey = 'fp_ws_support_message_updates_queue';
        $messageUpdatesQueue = Yii::$app->cache->get($messageUpdatesQueueKey);
        if (!is_array($messageUpdatesQueue)) {
            $messageUpdatesQueue = [];
        }
        $processedMu = [];
        foreach ($messageUpdatesQueue as $queueKeyId => $queueTimestamp) {
            if (($now - $queueTimestamp) > 5) {
                continue;
            }
            $parts = explode('_', $queueKeyId, 2);
            if (count($parts) !== 2) {
                continue;
            }
            list($ticketId, $messageId) = $parts;
            $updateCacheKey = 'fp_ws_support_message_update_' . $ticketId . '_' . $messageId;
            $updateData = Yii::$app->cache->get($updateCacheKey);
            if ($updateData && isset($updateData['type']) && $updateData['type'] === 'support.message.updated') {
                if (isset($updateData['timestamp']) && ($now - $updateData['timestamp']) < 5) {
                    Yii::$app->cache->delete($updateCacheKey);
                    $this->dispatchMessageUpdated((int) $updateData['ticketId'], (int) $updateData['messageId']);
                    $processedMu[] = $queueKeyId;
                }
            }
        }
        if (!empty($processedMu)) {
            foreach ($processedMu as $id) {
                unset($messageUpdatesQueue[$id]);
            }
            if (empty($messageUpdatesQueue)) {
                Yii::$app->cache->delete($messageUpdatesQueueKey);
            } else {
                Yii::$app->cache->set($messageUpdatesQueueKey, $messageUpdatesQueue, 10);
            }
        }

        $messageDeletesQueueKey = 'fp_ws_support_message_deletes_queue';
        $messageDeletesQueue = Yii::$app->cache->get($messageDeletesQueueKey);
        if (!is_array($messageDeletesQueue)) {
            $messageDeletesQueue = [];
        }
        $processedMd = [];
        foreach ($messageDeletesQueue as $queueKeyId => $queueTimestamp) {
            if (($now - $queueTimestamp) > 5) {
                continue;
            }
            $parts = explode('_', $queueKeyId, 2);
            if (count($parts) !== 2) {
                continue;
            }
            list($ticketId, $messageId) = $parts;
            $deleteCacheKey = 'fp_ws_support_message_delete_' . $ticketId . '_' . $messageId;
            $deleteData = Yii::$app->cache->get($deleteCacheKey);
            if ($deleteData && isset($deleteData['type']) && $deleteData['type'] === 'support.message.deleted') {
                if (isset($deleteData['timestamp']) && ($now - $deleteData['timestamp']) < 5) {
                    Yii::$app->cache->delete($deleteCacheKey);
                    $this->dispatchMessageDeleted((int) $deleteData['ticketId'], (int) $deleteData['messageId']);
                    $processedMd[] = $queueKeyId;
                }
            }
        }
        if (!empty($processedMd)) {
            foreach ($processedMd as $id) {
                unset($messageDeletesQueue[$id]);
            }
            if (empty($messageDeletesQueue)) {
                Yii::$app->cache->delete($messageDeletesQueueKey);
            } else {
                Yii::$app->cache->set($messageDeletesQueueKey, $messageDeletesQueue, 10);
            }
        }

        $purchasesQueueKey = 'fp_ws_purchases_queue';
        $purchasesQueue = Yii::$app->cache->get($purchasesQueueKey);
        if (!is_array($purchasesQueue)) {
            $purchasesQueue = [];
        }
        $processedPr = [];
        foreach ($purchasesQueue as $userId => $queueTimestamp) {
            if (($now - $queueTimestamp) > 5) {
                continue;
            }
            $purchaseCacheKey = 'fp_ws_purchase_' . $userId;
            $purchaseData = Yii::$app->cache->get($purchaseCacheKey);
            if ($purchaseData && isset($purchaseData['type']) && $purchaseData['type'] === 'purchase.completed') {
                if (isset($purchaseData['timestamp']) && ($now - $purchaseData['timestamp']) < 5) {
                    Yii::$app->cache->delete($purchaseCacheKey);
                    $this->dispatchPurchase((int) $purchaseData['userId'], $purchaseData['newBalance'] ?? null);
                    $processedPr[] = $userId;
                }
            }
        }
        if (!empty($processedPr)) {
            foreach ($processedPr as $uid) {
                unset($purchasesQueue[$uid]);
            }
            if (empty($purchasesQueue)) {
                Yii::$app->cache->delete($purchasesQueueKey);
            } else {
                Yii::$app->cache->set($purchasesQueueKey, $purchasesQueue, 10);
            }
        }

        $storeInvQueueKey = 'fp_ws_store_inventory_queue';
        $storeInvQueue = Yii::$app->cache->get($storeInvQueueKey);
        if (!is_array($storeInvQueue)) {
            $storeInvQueue = [];
        }
        $processedSi = [];
        foreach ($storeInvQueue as $userId => $queueTimestamp) {
            if (($now - $queueTimestamp) > 5) {
                continue;
            }
            $storeCacheKey = 'fp_ws_store_inventory_' . $userId;
            $storeData = Yii::$app->cache->get($storeCacheKey);
            if ($storeData && isset($storeData['type']) && $storeData['type'] === 'store.inventory.changed') {
                if (isset($storeData['timestamp']) && ($now - $storeData['timestamp']) < 5) {
                    Yii::$app->cache->delete($storeCacheKey);
                    $this->dispatchStoreInventoryChanged(
                        (int) $storeData['userId'],
                        isset($storeData['reason']) ? (string) $storeData['reason'] : 'purchase'
                    );
                    $processedSi[] = $userId;
                }
            }
        }
        if (!empty($processedSi)) {
            foreach ($processedSi as $uid) {
                unset($storeInvQueue[$uid]);
            }
            if (empty($storeInvQueue)) {
                Yii::$app->cache->delete($storeInvQueueKey);
            } else {
                Yii::$app->cache->set($storeInvQueueKey, $storeInvQueue, 10);
            }
        }

        $userBlocksQueueKey = 'fp_ws_user_blocks_queue';
        $userBlocksQueue = Yii::$app->cache->get($userBlocksQueueKey);
        if (!is_array($userBlocksQueue)) {
            $userBlocksQueue = [];
        }
        $processedUb = [];
        foreach ($userBlocksQueue as $queueKeyId => $queueTimestamp) {
            if (($now - $queueTimestamp) > 5) {
                continue;
            }
            $parts = explode('_', $queueKeyId, 2);
            if (count($parts) !== 2) {
                continue;
            }
            list($userId, $blockType) = $parts;
            $blockCacheKey = 'fp_ws_user_block_' . $userId . '_' . $blockType;
            $blockData = Yii::$app->cache->get($blockCacheKey);
            if ($blockData && isset($blockData['type']) && $blockData['type'] === 'support.user.blocked') {
                if (isset($blockData['timestamp']) && ($now - $blockData['timestamp']) < 5) {
                    Yii::$app->cache->delete($blockCacheKey);
                    $this->dispatchUserBlocked(
                        (int) $blockData['userId'],
                        (string) $blockData['blockType'],
                        $blockData['blocked'],
                        $blockData['blockedAt'] ?? null
                    );
                    $processedUb[] = $queueKeyId;
                }
            }
        }
        if (!empty($processedUb)) {
            foreach ($processedUb as $id) {
                unset($userBlocksQueue[$id]);
            }
            if (empty($userBlocksQueue)) {
                Yii::$app->cache->delete($userBlocksQueueKey);
            } else {
                Yii::$app->cache->set($userBlocksQueueKey, $userBlocksQueue, 10);
            }
        }

        $readReceiptsQueueKey = 'fp_ws_support_messages_read_queue';
        $readReceiptsQueue = Yii::$app->cache->get($readReceiptsQueueKey);
        if (!is_array($readReceiptsQueue)) {
            $readReceiptsQueue = [];
        }
        $processedRr = [];
        foreach ($readReceiptsQueue as $uniq => $queueTimestamp) {
            if (($now - $queueTimestamp) > 8) {
                continue;
            }
            $readCacheKey = 'fp_ws_support_messages_read_payload_' . $uniq;
            $readData = Yii::$app->cache->get($readCacheKey);
            if ($readData && isset($readData['type']) && $readData['type'] === 'support.messages.read') {
                if (isset($readData['timestamp']) && ($now - $readData['timestamp']) < 8) {
                    Yii::$app->cache->delete($readCacheKey);
                    $this->dispatchSupportMessagesRead(
                        (int) $readData['ticketId'],
                        (int) $readData['readerUserId'],
                        isset($readData['readStates']) && is_array($readData['readStates']) ? $readData['readStates'] : []
                    );
                    $processedRr[] = $uniq;
                }
            }
        }
        if (!empty($processedRr)) {
            foreach ($processedRr as $uniq) {
                unset($readReceiptsQueue[$uniq]);
            }
            if (empty($readReceiptsQueue)) {
                Yii::$app->cache->delete($readReceiptsQueueKey);
            } else {
                Yii::$app->cache->set($readReceiptsQueueKey, $readReceiptsQueue, 15);
            }
        }
    }

    private function log(string $message): void
    {
        echo date('Y-m-d H:i:s') . " [FrontendPushGateway] {$message}" . PHP_EOL;
    }

    // ——— Статические методы постановки в очередь (вызывать из API рядом с NotificationServer) ———

    public static function queueSupportMessage(int $ticketNumber, int $messageId, int $senderUserId, ?int $ownerUserId): bool
    {
        try {
            $cacheKey = 'fp_ws_support_message_' . $ticketNumber;
            $data = [
                'type' => 'support.message',
                'ticketId' => $ticketNumber,
                'messageId' => $messageId,
                'userId' => $senderUserId,
                'ownerUserId' => $ownerUserId,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 90);
            $queueKey = 'fp_ws_support_messages_queue';
            $queue = Yii::$app->cache->get($queueKey);
            if (!is_array($queue)) {
                $queue = [];
            }
            $queue[$ticketNumber] = time();
            if (count($queue) > 1000) {
                arsort($queue);
                $queue = array_slice($queue, 0, 1000, true);
            }
            Yii::$app->cache->set($queueKey, $queue, 90);
            return true;
        } catch (\Exception $ex) {
            error_log('FrontendPushGatewayServer::queueSupportMessage: ' . $ex->getMessage());
            return false;
        }
    }

    public static function queueTicketStatus(int $ticketNumber, string $status): bool
    {
        try {
            $cacheKey = 'fp_ws_support_status_' . $ticketNumber;
            $data = [
                'type' => 'support.status',
                'ticketId' => $ticketNumber,
                'status' => $status,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 10);
            $queueKey = 'fp_ws_support_status_queue';
            $queue = Yii::$app->cache->get($queueKey);
            if (!is_array($queue)) {
                $queue = [];
            }
            $queue[$ticketNumber] = time();
            if (count($queue) > 1000) {
                arsort($queue);
                $queue = array_slice($queue, 0, 1000, true);
            }
            Yii::$app->cache->set($queueKey, $queue, 10);
            return true;
        } catch (\Exception $ex) {
            error_log('FrontendPushGatewayServer::queueTicketStatus: ' . $ex->getMessage());
            return false;
        }
    }

    public static function queueNewTicket(int $ticketNumber, int $creatorUserId): bool
    {
        try {
            $cacheKey = 'fp_ws_support_new_ticket_' . $ticketNumber;
            $data = [
                'type' => 'support.new_ticket',
                'ticketId' => $ticketNumber,
                'userId' => $creatorUserId,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 10);
            $queueKey = 'fp_ws_support_new_tickets_queue';
            $queue = Yii::$app->cache->get($queueKey);
            if (!is_array($queue)) {
                $queue = [];
            }
            $queue[$ticketNumber] = time();
            if (count($queue) > 1000) {
                arsort($queue);
                $queue = array_slice($queue, 0, 1000, true);
            }
            Yii::$app->cache->set($queueKey, $queue, 10);
            return true;
        } catch (\Exception $ex) {
            error_log('FrontendPushGatewayServer::queueNewTicket: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Сигнал фронту перезагрузить GET /v1/store/items (страница /store).
     */
    public static function queueStoreInventoryRefresh(int $userId, string $reason = 'purchase'): bool
    {
        try {
            $cacheKey = 'fp_ws_store_inventory_' . $userId;
            $data = [
                'type' => 'store.inventory.changed',
                'userId' => $userId,
                'reason' => $reason,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 10);
            $queueKey = 'fp_ws_store_inventory_queue';
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

            return true;
        } catch (\Exception $ex) {
            error_log('FrontendPushGatewayServer::queueStoreInventoryRefresh: ' . $ex->getMessage());

            return false;
        }
    }

    public static function queuePurchase(int $userId, $newBalance): bool
    {
        try {
            $cacheKey = 'fp_ws_purchase_' . $userId;
            $data = [
                'type' => 'purchase.completed',
                'userId' => $userId,
                'newBalance' => $newBalance,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 10);
            $queueKey = 'fp_ws_purchases_queue';
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
            return true;
        } catch (\Exception $ex) {
            error_log('FrontendPushGatewayServer::queuePurchase: ' . $ex->getMessage());
            return false;
        }
    }

    public static function queueUserBlocked(int $userId, string $blockType, $blocked, $blockedAt): bool
    {
        try {
            $cacheKey = 'fp_ws_user_block_' . $userId . '_' . $blockType;
            $data = [
                'type' => 'support.user.blocked',
                'userId' => $userId,
                'blockType' => $blockType,
                'blocked' => $blocked,
                'blockedAt' => $blockedAt,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 10);
            $queueKey = 'fp_ws_user_blocks_queue';
            $queue = Yii::$app->cache->get($queueKey);
            if (!is_array($queue)) {
                $queue = [];
            }
            $qid = $userId . '_' . $blockType;
            $queue[$qid] = time();
            if (count($queue) > 1000) {
                arsort($queue);
                $queue = array_slice($queue, 0, 1000, true);
            }
            Yii::$app->cache->set($queueKey, $queue, 10);
            return true;
        } catch (\Exception $ex) {
            error_log('FrontendPushGatewayServer::queueUserBlocked: ' . $ex->getMessage());
            return false;
        }
    }

    public static function queueMessageUpdate(int $ticketNumber, int $messageId): bool
    {
        try {
            $cacheKey = 'fp_ws_support_message_update_' . $ticketNumber . '_' . $messageId;
            $data = [
                'type' => 'support.message.updated',
                'ticketId' => $ticketNumber,
                'messageId' => $messageId,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 10);
            $queueKey = 'fp_ws_support_message_updates_queue';
            $queue = Yii::$app->cache->get($queueKey);
            if (!is_array($queue)) {
                $queue = [];
            }
            $queueKeyId = $ticketNumber . '_' . $messageId;
            $queue[$queueKeyId] = time();
            if (count($queue) > 1000) {
                arsort($queue);
                $queue = array_slice($queue, 0, 1000, true);
            }
            Yii::$app->cache->set($queueKey, $queue, 10);
            return true;
        } catch (\Exception $ex) {
            error_log('FrontendPushGatewayServer::queueMessageUpdate: ' . $ex->getMessage());
            return false;
        }
    }

    public static function queueMessageDelete(int $ticketNumber, int $messageId): bool
    {
        try {
            $cacheKey = 'fp_ws_support_message_delete_' . $ticketNumber . '_' . $messageId;
            $data = [
                'type' => 'support.message.deleted',
                'ticketId' => $ticketNumber,
                'messageId' => $messageId,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 10);
            $queueKey = 'fp_ws_support_message_deletes_queue';
            $queue = Yii::$app->cache->get($queueKey);
            if (!is_array($queue)) {
                $queue = [];
            }
            $queueKeyId = $ticketNumber . '_' . $messageId;
            $queue[$queueKeyId] = time();
            if (count($queue) > 1000) {
                arsort($queue);
                $queue = array_slice($queue, 0, 1000, true);
            }
            Yii::$app->cache->set($queueKey, $queue, 10);
            return true;
        } catch (\Exception $ex) {
            error_log('FrontendPushGatewayServer::queueMessageDelete: ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * @param array<int, array{messageId: int, senderUserId: int, is_read: bool}> $readStates
     */
    public static function queueSupportMessagesRead(int $ticketNumber, int $readerUserId, array $readStates): bool
    {
        try {
            $uniq = str_replace('.', '_', uniqid('rr', true));
            $cacheKey = 'fp_ws_support_messages_read_payload_' . $uniq;
            $data = [
                'type' => 'support.messages.read',
                'ticketId' => $ticketNumber,
                'readerUserId' => $readerUserId,
                'readStates' => $readStates,
                'timestamp' => time(),
            ];
            Yii::$app->cache->set($cacheKey, $data, 15);
            $queueKey = 'fp_ws_support_messages_read_queue';
            $queue = Yii::$app->cache->get($queueKey);
            if (!is_array($queue)) {
                $queue = [];
            }
            $queue[$uniq] = time();
            if (count($queue) > 1000) {
                arsort($queue);
                $queue = array_slice($queue, 0, 1000, true);
            }
            Yii::$app->cache->set($queueKey, $queue, 15);

            return true;
        } catch (\Exception $ex) {
            error_log('FrontendPushGatewayServer::queueSupportMessagesRead: ' . $ex->getMessage());

            return false;
        }
    }
}
