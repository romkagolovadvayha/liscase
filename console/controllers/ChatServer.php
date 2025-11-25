<?php
namespace console\controllers;

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
use Ratchet\WebSocket\Version\RFC6455\Frame;
use yii\db\Exception as DbException;
use PDOException;

class ChatServer extends WebSocketServer
{
    /** @var ChatServer Singleton instance */
    private static $instance = null;
    
    /** @var int секунд без активности до закрытия */
    private $idleCloseSeconds = 45; // NEW

    /** @var array Индекс клиентов по user_id для быстрого поиска */
    private $clientsByUserId = [];
    
    /** @var array Индекс клиентов по chat для быстрого поиска */
    private $clientsByChat = [];
    
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
     * Обработка отложенных сообщений из кеша
     */
    private function processQueuedMessage($client, $data)
    {
        try {
            $client->send(json_encode($data));
        } catch (\Throwable $e) {
            $this->log("Error sending queued message: " . $e->getMessage());
        }
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
            $e->client->user = null;
            $e->client->chat = null;
            $e->client->launcher = false;

            // heartbeat state
            $e->client->lastPong = time();
            $e->client->alive = true;
        });
        $this->on(self::EVENT_CLIENT_DISCONNECTED, function(WSClientEvent $e) {
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
                $this->log("connections after 60s: " . count($this->clients));
            });

            $interval = 15; // каждые 15 сек пингуем
            $loop->addPeriodicTimer($interval, function () {
                $now = time();
                foreach ($this->clients as $client) {
                    // Закрываем по реальному idle-таймауту (не по счётчику) // CHANGED
                    $idle = $now - (isset($client->lastPong) ? $client->lastPong : 0);
                    if ($idle >= $this->idleCloseSeconds) {
                        try { $client->close(1000, 'heartbeat timeout'); } catch (\Throwable $e) {}
                        continue;
                    }

                    // Пробуем WS-ping фрейм (браузер авто-ответит pong) // NEW
                    try {
                        $client->send(new Frame('', true, Frame::OP_PING));
                    } catch (\Throwable $e) {
                        $this->log("ping frame send failed: " . $e->getMessage());
                        try { $client->close(1011, 'send failed'); } catch (\Throwable $e2) {}
                        continue;
                    }

                    // Оставляем и app-уровень ping (на случай не-браузерных клиентов) // NEW
                    try {
                        $client->send(json_encode(['type' => 'ping', 'ts' => $now]));
                    } catch (\Throwable $e) {
                        $this->log("app ping send failed: " . $e->getMessage());
                        try { $client->close(1011, 'send failed'); } catch (\Throwable $e2) {}
                    }
                }
            });
            
            // Отправка обновлений онлайна из кеша каждые 5 секунд
            $loop->addPeriodicTimer(5, function () {
                try {
                    $cacheKey = 'ws_online_data';
                    $data = Yii::$app->cache->get($cacheKey);
                    
                    if ($data && (time() - $data['timestamp']) < 10) {
                        $response = json_encode([
                            'type'    => 'update.online',
                            'code'    => 200,
                            'servers' => $data['servers'],
                            'total'   => $data['total'],
                        ]);
                        
                        // Отправляем всем клиентам
                        foreach ($this->clients as $client) {
                            try {
                                $client->send($response);
                            } catch (\Throwable $e) {
                                // Молча пропускаем
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $this->log("Error broadcasting online update: " . $e->getMessage());
                }
            });
            
            // Обработка support событий из кеша каждую секунду
            $loop->addPeriodicTimer(1, function () {
                try {
                    
                    // Обрабатываем через commandSupportStatus, commandTicketUpdate, commandChatUpdate
                    foreach ($this->clients as $client) {
                        try {
                            // Проверяем разные типы кешированных сообщений
                            $allKeys = [
                                'ws_support_status',
                                'ws_ticket_update',
                                'ws_chat_update'
                            ];
                            
                            // Для каждого клиента проверяем есть ли для него сообщения
                            if (!empty($client->chat)) {
                                $statusKey = 'ws_support_status_' . $client->chat;
                                $statusData = Yii::$app->cache->get($statusKey);
                                if ($statusData && (time() - $statusData['timestamp']) < 5) {
                                    if (!isset($statusData['sent'])) {
                                        // Отправляем всем клиентам в этом чате
                                        $chatClients = $this->getClientsByChat($client->chat);
                                        foreach ($chatClients as $chatClient) {
                                            $this->processQueuedMessage($chatClient, $statusData);
                                        }
                                        $statusData['sent'] = true;
                                        Yii::$app->cache->set($statusKey, $statusData, 5);
                                    }
                                }
                                
                                // Chat updates для конкретного чата
                                $chatKey = 'ws_chat_update_' . $client->chat;
                                $chatData = Yii::$app->cache->get($chatKey);
                                if ($chatData && isset($chatData['timestamp']) && (time() - $chatData['timestamp']) < 5) {
                                    if (!isset($chatData['sent'])) {
                                        // Отправляем всем клиентам в этом чате
                                        $chatClients = $this->getClientsByChat($client->chat);
                                        foreach ($chatClients as $chatClient) {
                                            $this->processQueuedMessage($chatClient, [
                                                'type' => 'chat',
                                                'messageId' => $chatData['messageId'] ?? null,
                                            ]);
                                        }
                                        $chatData['sent'] = true;
                                        Yii::$app->cache->set($chatKey, $chatData, 5);
                                    }
                                }
                            }
                            
                            // Ticket updates для конкретного пользователя
                            if (!empty($client->user)) {
                                $ticketKey = 'ws_ticket_update_' . $client->user->id;
                                $ticketData = Yii::$app->cache->get($ticketKey);
                                if ($ticketData && (time() - $ticketData['timestamp']) < 5) {
                                    if (!isset($ticketData['sent'])) {
                                        // Отправляем всем клиентам пользователя
                                        $userClients = $this->getClientsByUserId($client->user->id);
                                        foreach ($userClients as $userClient) {
                                            $this->processQueuedMessage($userClient, $ticketData);
                                        }
                                        $ticketData['sent'] = true;
                                        Yii::$app->cache->set($ticketKey, $ticketData, 5);
                                    }
                                }
                                
                                // Balance updates для конкретного пользователя
                                $balanceKey = 'ws_balance_update_' . $client->user->id;
                                $balanceData = Yii::$app->cache->get($balanceKey);
                                if ($balanceData && (time() - $balanceData['timestamp']) < 30) {
                                    // Проверяем не отправлено ли уже
                                    if (!isset($balanceData['sent'])) {
                                        // Отправляем всем клиентам пользователя
                                        $userClients = $this->getClientsByUserId($client->user->id);
                                        foreach ($userClients as $userClient) {
                                            $this->processQueuedMessage($userClient, $balanceData);
                                        }
                                        // Помечаем как отправленное
                                        $balanceData['sent'] = true;
                                        Yii::$app->cache->set($balanceKey, $balanceData, 5);
                                    }
                                }
                                
                                // Buy/Activated drop updates - используем список дропов
                                $listKey = 'ws_drops_list_' . $client->user->id;
                                $dropsList = Yii::$app->cache->get($listKey);
                                if ($dropsList && is_array($dropsList) && count($dropsList) > 0) {
                                    $userClients = $this->getClientsByUserId($client->user->id);
                                    
                                    foreach ($dropsList as $dropId) {
                                        // Проверяем buy drop
                                        $buyKey = 'ws_buy_drop_' . $client->user->id . '_' . $dropId;
                                        $buyData = Yii::$app->cache->get($buyKey);
                                        if ($buyData && isset($buyData['timestamp']) && (time() - $buyData['timestamp']) < 30) {
                                            // Проверяем не отправлено ли уже
                                            if (!isset($buyData['sent'])) {
                                                // Вызываем commandBuyDrop для рендеринга и отправки
                                                $this->commandBuyDrop($client, json_encode($buyData));
                                                // Помечаем как отправленное
                                                $buyData['sent'] = true;
                                                Yii::$app->cache->set($buyKey, $buyData, 5);
                                            }
                                        }
                                        
                                        // Проверяем activated drop
                                        $activatedKey = 'ws_activated_drop_' . $client->user->id . '_' . $dropId;
                                        $activatedData = Yii::$app->cache->get($activatedKey);
                                        if ($activatedData && isset($activatedData['timestamp']) && (time() - $activatedData['timestamp']) < 30) {
                                            // Проверяем не отправлено ли уже
                                            if (!isset($activatedData['sent'])) {
                                                // Отправляем всем клиентам пользователя
                                                foreach ($userClients as $userClient) {
                                                    $this->processQueuedMessage($userClient, $activatedData);
                                                }
                                                // Помечаем как отправленное
                                                $activatedData['sent'] = true;
                                                Yii::$app->cache->set($activatedKey, $activatedData, 5);
                                            }
                                        }
                                        
                                        // Проверяем return drop
                                        $returnKey = 'ws_return_drop_' . $client->user->id . '_' . $dropId;
                                        $returnData = Yii::$app->cache->get($returnKey);
                                        if ($returnData && isset($returnData['timestamp']) && (time() - $returnData['timestamp']) < 30) {
                                            // Проверяем не отправлено ли уже
                                            if (!isset($returnData['sent'])) {
                                                // Отправляем всем клиентам пользователя
                                                foreach ($userClients as $userClient) {
                                                    $this->processQueuedMessage($userClient, $returnData);
                                                }
                                                // Помечаем как отправленное
                                                $returnData['sent'] = true;
                                                Yii::$app->cache->set($returnKey, $returnData, 5);
                                            }
                                        }
                                    }
                                }
                            }
                            
                            // Launcher updates
                            if (!empty($client->launcher)) {
                                // Проверяем launcher updates (они с timestamp в ключе, проверяем последние)
                                for ($i = 0; $i < 10; $i++) {
                                    $launcherKey = 'ws_launcher_update_' . (time() - $i);
                                    $launcherData = Yii::$app->cache->get($launcherKey);
                                    if ($launcherData && (time() - $launcherData['timestamp']) < 5) {
                                        $this->processQueuedMessage($client, $launcherData);
                                        break; // Отправили, выходим
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
        });
    }

    protected function getCommand(ConnectionInterface $from, $msg)
    {
        // Любое входящее — клиент «жив»
        $from->lastPong = time(); // CHANGED (оставляем)
        $from->alive = true;

        $request = json_decode($msg, true) ?: [];

        // Принимаем pong и как action, и как type // NEW
        if (
            (isset($request['action']) && $request['action'] === 'pong') ||
            (isset($request['type']) && $request['type'] === 'pong')
        ) {
            // commandPong будет вызван через return 'pong'
            return 'pong';
        }

        return !empty($request['action']) ? $request['action'] : parent::getCommand($from, $msg);
    }

    public function commandSubscription(ConnectionInterface $client, $msg)
    {
        try {
            $request = json_decode($msg, true);
            $result = ['message' => ''];

            if (!empty($client->user) && !empty($request['chat'])) {
                // Кешируем поиск тикета на 30 секунд
                $cacheKey = 'ws_ticket_' . $request['chat'];
                $ticket = Yii::$app->cache->getOrSet($cacheKey, function() use ($request) {
                    return Support::findByNumber($request['chat']);
                }, 30);

                // Если тикет существует - проверяем права доступа
                if ($ticket) {
                    if ($client->user->canRoles([Role::ROLE_ADMIN]) || 
                        $client->user->canRoles([Role::ROLE_MODERATOR]) || 
                        $ticket->user_id == $client->user->id
                    ) {
                        $client->chat = $request['chat'];
                        
                        // Добавляем в индекс для быстрого поиска
                        $this->indexClientByChat($client);
                    }
                } else {
                    // Если тикета нет - разрешаем подписку (тикет создастся при первом сообщении)
                    $client->chat = $request['chat'];
                    
                    // Добавляем в индекс для быстрого поиска
                    $this->indexClientByChat($client);
                }
            }
            
            $client->send(json_encode($result));
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
                  $client->send(json_encode([
                                                'type' => 'store.take',
                                                'code' => 500,
                                                'message' => Yii::t('common', "Товар вам не принадлежит!", [], $client->user->current_language),
                                                'id' => $model->id,
                                            ]));
                  return;
              }
              
              // Защита от повторного вывода: проверяем статус предмета
              if ($model->status != UserDrop::STATUS_ACTIVE) {
                  $statusText = UserDrop::getStatusList()[$model->status] ?? 'Недоступен';
                  $client->send(json_encode([
                                                'type' => 'store.take',
                                                'code' => 500,
                                                'message' => Yii::t('common', "Товар уже был выведен или недоступен! Статус: {status}", ['status' => $statusText], $client->user->current_language),
                                                'id' => $model->id,
                                            ]));
                  return;
              }
              
              // Атомарная блокировка предмета на время обработки
              $lockKey = 'commandGetDrop_lock_' . $model->id;
              if (Yii::$app->cache->get($lockKey)) {
                  $client->send(json_encode([
                                                'type' => 'store.take',
                                                'code' => 500,
                                                'message' => Yii::t('common', "Предмет уже обрабатывается, подождите немного!", [], $client->user->current_language),
                                                'id' => $model->id,
                                            ]));
                  return;
              }
              Yii::$app->cache->set($lockKey, true, 10); // Блокируем на 10 секунд
              
              if (empty($model->user->server)) {
                  Yii::$app->cache->delete($lockKey); // Снимаем блокировку
                  $client->send(json_encode([
                                                'type' => 'store.take',
                                                'code' => 500,
                                                'message' => Yii::t('common', "Мы не нашли вас на сервере!", [], $client->user->current_language),
                                                'id' => $model->id,
                                            ]));
                  return;
              }
              if (DropBlocked::getBlocked($model->drop_id, $model->user->server->id, true)) {
                  Yii::$app->cache->delete($lockKey); // Снимаем блокировку
                  $client->send(json_encode([
                                                'type' => 'store.take',
                                                'code' => 500,
                                                'message' => Yii::t('common', "Товар в вайп-блоке!", [], $client->user->current_language),
                                                'id' => $model->id,
                                            ]));
                  return;
              }

              $cacheKey = 'commandGetDrop_kd_' . $model->user->id;
              $count = Yii::$app->cache->get($cacheKey) ?? 0;
              if ($count > 5) {
                  Yii::$app->cache->delete($lockKey); // Снимаем блокировку
                  $client->send(json_encode([
                                                'type' => 'store.take',
                                                'code' => 500,
                                                'message' => Yii::t('common', "Нельзя выполнять действия слишком часто! Подождите 30 секунд.", [], $client->user->current_language),
                                                'id' => $model->id,
                                            ]));
                  return;
              }
              Yii::$app->cache->set($cacheKey, $count + 1, 30);

              if ($client->user->canRoles([Role::ROLE_ADMIN]) || $client->user->canRoles([Role::ROLE_MODERATOR]) || $model->user_id == $client->user->id) {
                  $model->status = UserDrop::STATUS_WAIT;
                  $model->save(false);
                  $isBlockedBuilding = $model->drop[0]->is_blocked_building ? 'true' : 'false';
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
                          $client->send(json_encode([
                                                        'type' => 'store.take',
                                                        'code' => 500,
                                                        'message' => Yii::t('common', "Произошла ошибка, попробуйте позже!", [], $client->user->current_language),
                                                        'id' => $model->id,
                                                    ]));
                            $model->status = UserDrop::STATUS_ACTIVE;
                            $model->save(false);                        
                          return;
                      }
                      $data = json_decode(json_decode($response, 1)['result'], 1);
                      if (!isset($data['success'])) {
                          Yii::$app->cache->delete($lockKey); // Снимаем блокировку при ошибке
                          $client->send(json_encode([
                                                        'type' => 'store.take',
                                                        'code' => 500,
                                                        'message' => Yii::t('common', "Произошла ошибка, попробуйте позже!", [], $client->user->current_language),
                                                        'id' => $model->id,
                                                    ]));
                          $model->status = UserDrop::STATUS_ACTIVE;
                          $model->save(false);        
                          return;
                      }
                      if (!$data['success']) {
                          Yii::$app->cache->delete($lockKey); // Снимаем блокировку при ошибке
                          $client->send(json_encode([
                                                        'type' => 'store.take',
                                                        'code' => 500,
                                                        'message' => $data['error'],
                                                        'id' => $model->id,
                                                    ]));
                            $model->status = UserDrop::STATUS_ACTIVE;
                            $model->save(false);        
                          return;
                      }
                      if ($data['success']) {
                          // Успешная выдача - меняем статус предмета на "Отправлен"
                          Yii::$app->cache->delete($lockKey); // Снимаем блокировку
                          $client->send(json_encode([
                                                        'type' => 'store.take',
                                                        'code' => 200,
                                                        'message' => Yii::t('common', "Товар успешно получен!", [], $client->user->current_language),
                                                        'id' => $model->id,
                                                    ]));
                          return;
                      }
                  } catch (\Exception $e) {
                      Yii::$app->cache->delete($lockKey); // Снимаем блокировку при исключении
                      Yii::$app->telegramChats->sendMessage($e->getFile() . ":" . $e->getLine() . "; " . $e->getMessage() . "; " . $model->id . "; " . $model->user->steam_id . "; " . $command . "; " . $response);
                      $client->send(json_encode([
                                                    'type' => 'store.take',
                                                    'code' => 500,
                                                    'message' => Yii::t('common', "Произошла ошибка, попробуйте позже!", [], $client->user->current_language),
                                                    'id' => $model->id,
                                                ]));
                  }

                  return;
              }
          }

          $client->send( json_encode($result) );
      } catch (\Exception $e) {
          Yii::$app->telegramChats->sendMessage('commandGetDrop: ' . $e->getFile() . ':' . $e->getLine() . ' ' . $e->getMessage());
          echo "commandGetDrop:" . $e->getLine() . ":" . $e->getMessage() . PHP_EOL;
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
                if ($user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR])) {
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
        $client->send(json_encode($result));
    }

    public function commandReturnDrop(ConnectionInterface $client, $msg)
    {
        try {
            $request = json_decode($msg, true);
            $result = ['message' => ''];

            if (!empty($client->user) && !empty($request['id'])) {
                $model = UserDrop::findOne($request['id']);
                
                if (!$model) {
                    $client->send(json_encode([
                        'type' => 'store.return.item',
                        'code' => 500,
                        'message' => Yii::t('common', "Товар не найден!", [], $client->user->current_language),
                        'id' => $request['id'],
                    ]));
                    return;
                }

                if ($client->user->id != $model->user->id) {
                    $client->send(json_encode([
                        'type' => 'store.return.item',
                        'code' => 500,
                        'message' => Yii::t('common', "Товар вам не принадлежит!", [], $client->user->current_language),
                        'id' => $model->id,
                    ]));
                    return;
                }

                if (!empty($model->box_id) || !empty($model->sets_id) || !empty($model->parent_drop_id)) {
                    $client->send(json_encode([
                        'type' => 'store.return.item',
                        'code' => 500,
                        'message' => Yii::t('common', "Не подлежит возврату!", [], $client->user->current_language),
                        'id' => $model->id,
                    ]));
                    return;
                }

                if ($model->status !== UserDrop::STATUS_ACTIVE) {
                    $client->send(json_encode([
                        'type' => 'store.return.item',
                        'code' => 500,
                        'message' => Yii::t('common', "Не найдена вещь в корзине!", [], $client->user->current_language),
                        'id' => $model->id,
                    ]));
                    return;
                }

                // Выполняем возврат
                $userBalance = $model->user->getPersonalBalance();
                $this->_sellUserDrop($model, $userBalance->id);

                // Отправляем через websocket
                Yii::$app->queueProcess->push(new \common\components\queue\process\ReturnDropJob(['userDrop' => $model]));

                $client->send(json_encode([
                    'type' => 'store.return.item',
                    'code' => 200,
                    'message' => Yii::t('common', "Предмет успешно возвращен!", [], $client->user->current_language),
                    'id' => $model->id,
                ]));
            }
        } catch (\Exception $ex) {
            $this->log("ReturnDrop error: " . $ex->getMessage());
            if (!empty($request['id'])) {
                $client->send(json_encode([
                    'type' => 'store.return.item',
                    'code' => 500,
                    'message' => Yii::t('common', "Произошла ошибка при возврате товара!", [], $client->user->current_language ?? 'ru-RU'),
                    'id' => $request['id'],
                ]));
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
        /** @var \common\models\box\Drop $drop */
        foreach ($userDrop->drop as $drop) {
            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_SELL_DROP;
            $profit->amount = $drop->getRealPrice(false);
            $profit->user_balance_id = $userBalanceId;
            $profit->comment = Yii::t('common', 'Возврат предмета "{PARAMS_PREDNAME}"', [
                'PARAMS_PREDNAME' => Yii::t('database', $drop->name)
            ], 'ru-RU');
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);
        }
        $userDrop->status = UserDrop::STATUS_SELL;
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
        $result = ['message' => ''];

        $request = json_decode($msg, true);
        if (empty($client->chat) || empty($client->user) || empty($request['chatId'])) {
            $client->send(json_encode($result));
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
        
        $client->send(json_encode($result));
    }

    public function commandChatBlur(ConnectionInterface $client, $msg)
    {
        $result = ['message' => ''];

        $request = json_decode($msg, true);
        if (empty($client->chat) || empty($client->user) || empty($request['chatId'])) {
            $client->send(json_encode($result));
            return;
        }
        
        try {
            // Используем индекс для быстрого поиска клиентов по chat
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
        
        $client->send(json_encode($result));
    }

    public static function usernameClass($user) {
        if ($user->canRoles([Role::ROLE_ADMIN])) {
            return 'admin';
        }
        if ($user->canRoles([Role::ROLE_MODERATOR])) {
            return 'moder';
        }
        return '';
    }

    public function commandChat(ConnectionInterface $client, $msg)
    {
        try {
            $request = json_decode($msg, true);
            $result = ['message' => ''];

            if (empty($client->chat)) {
                $client->send(json_encode($result));
                return;
            }

            if (!empty($client->user) && !empty($request['message']) && $message = trim($request['message']) ) {
                $cacheKey = 'commandChat_' . $client->user->id;
                if (!empty(Yii::$app->cache->get($cacheKey))) {
                    $client->send(json_encode(['type' => 'error', 'error' => Yii::$app->cache->get($cacheKey)]));
                    return;
                }
                if (!$client->user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR])) {
                    Yii::$app->cache->set($cacheKey, Yii::t('common', "Нельзя отправлять сообщения слишком часто!", [], $client->user->current_language), 2);
                }
                /** @var User $user */
                $user = $client->user;
                if ($user->blocked_support || $user->status == User::STATUS_BLOCKED || strtotime($user->blocked_support_at) > time()) {
                    $client->send(json_encode(['type' => 'error', 'error' => Yii::t('common', "Ваш чат заблокирован")]));
                }
                $chat = Support::findByNumber($client->chat);
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
                $model->save();

                Yii::$app->queueProcess->push(new BeforeMessageJob([
                    'chatId' => $model->support_id,
                    'userId' => $model->user_id,
                    'message' => $model->message,
                    'username' => $user->username,
                    'chatNumber' => $chat->getNumber(),
                ]));

                SupportRead::createRecord($chat->user_id, $user->id, $model->id, $chat->id);
                $this->commandTicketUpdate($client, json_encode(['user_id' => $chat->user_id]));
                $hash = md5(time());
                foreach ($this->clients as $chatClient) {
                    if (empty($chatClient)) {
                        continue;
                    }
                    if (!empty($chatClient->user)) {
                        /** @var User $_user */
                        $_user = $chatClient->user;
                        if ($_user->id === $chat->user_id || $_user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR])) {
                            if ($user->id !== $_user->id) {
                                $chatClient->send(json_encode([
                                                              'type' => 'support_notifications',
                                                              'count' => Support::unreadAll($_user->id),
                                                              'chatId' => $chat->getNumber(),
                                                              'hash'    => $hash,
                                                          ]));
                            }
                        }
                    }
                    if (empty($chatClient->chat)) {
                        continue;
                    }
                    if ($chatClient->chat != $request['chatId']) {
                        continue;
                    }
                    SupportRead::readedAll($model->support_id, $chatClient->user->id);
                    $chatClient->send(json_encode([
                                                      'type' => 'chat',
                                                      'messageId' => $model->id,
                                                  ]));
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
     * Проверяет, является ли ошибка ошибкой "MySQL server has gone away"
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
               strpos($message, 'HY000') !== false && strpos($message, '2006') !== false;
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
                $client->send(json_encode($result));
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
                $user = $this->safeDbQuery(function() use ($request) {
                    return User::find()
                        ->where(['jwt' => $request['token'], 'steam_id' => $request['steam_id']])
                        ->limit(1)
                        ->one();
                });
            }

            if ($user) {
                $client->user = $user;
                if (isset($request['launcher'])) {
                    $client->launcher = $request['launcher'];
                }
                
                // Добавляем в индекс для быстрого поиска
                $this->indexClientByUserId($client);

            } else {
                $result['message'] = 'Invalid token';
                $client->send(json_encode($result));
            }
        } catch (\Exception $ex) {
            $this->log("Auth error: " . $ex->getMessage());
            $errorMessage = 'commandAuth: ' . $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage();
            
            // Отправляем ошибку в Telegram только если это не ошибка переподключения
            if (!$this->isGoneAwayError($ex)) {
                try {
                    Yii::$app->telegramChats->sendMessage($errorMessage);
                } catch (\Exception $telegramEx) {
                    $this->log("Failed to send error to Telegram: " . $telegramEx->getMessage());
                }
            }
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