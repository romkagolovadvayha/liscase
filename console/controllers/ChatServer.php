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
use Yii;
use common\components\helpers\Role;
use common\models\support\Support;
use common\models\user\User;
use consik\yii2websocket\events\WSClientEvent;
use consik\yii2websocket\WebSocketServer;
use Ratchet\ConnectionInterface;
use yii\base\BaseObject;
use Ratchet\WebSocket\Version\RFC6455\Frame;

class ChatServer extends WebSocketServer
{
    /** @var int секунд без активности до закрытия */
    private $idleCloseSeconds = 120; // Увеличено с 45 до 120 секунд
    
    /** @var bool включить отладочные логи */
    private $debugMode = false; // Отключаем лишние логи для производительности
    
    /** @var int максимальное количество соединений */
    private $maxConnections = 2000;

    private function log($m, $force = false) {
        if ($this->debugMode || $force) {
            echo date('Y-m-d H:i:s') . " [WS] {$m}" . PHP_EOL;
        }
    }

    public function init()
    {
        parent::init();

        $this->on(self::EVENT_CLIENT_CONNECTED, function(WSClientEvent $e) {
            // Проверка лимита соединений
            if (count($this->clients) >= $this->maxConnections) {
                $this->log("Max connections reached ({$this->maxConnections}), rejecting new connection", true);
                try {
                    $e->client->close(1008, 'Server is full');
                } catch (\Throwable $ex) {}
                return;
            }
            
            $e->client->user = null;
            $e->client->chat = null;
            $e->client->launcher = false;

            // heartbeat state
            $e->client->lastPong = time();
            $e->client->alive = true;
            $e->client->connectionTime = time();
        });
        $this->on(self::EVENT_CLIENT_DISCONNECTED, function(WSClientEvent $e) {
            // Оптимизация: не отправляем chatBlur если нет чата
            if (empty($e->client->chat) || empty($e->client->user)) {
                return;
            }
            
            foreach ($this->clients as $chatClient) {
                if (empty($chatClient->chat) || empty($chatClient->user)) {
                    continue;
                }
                if ($chatClient->chat !== $e->client->chat || $e->client->user->id === $chatClient->user->id) {
                    continue;
                }
                try {
                    $chatClient->send(json_encode(['type' => 'chatBlur']));
                } catch (\Throwable $ex) {
                    // Игнорируем ошибки отправки при отключении
                }
            }
        });

        // После старта сокета есть loop
        $this->on(self::EVENT_WEBSOCKET_OPEN, function () {
            /** @var \Ratchet\Server\IoServer $io */
            $io = $this->server;
            $loop = $io->loop;
            
            $this->log("WebSocket server started on port {$this->port}", true);

            // Мониторинг соединений каждую минуту
            $loop->addPeriodicTimer(60, function () {
                $count = count($this->clients);
                $this->log("Active connections: {$count}/{$this->maxConnections}", true);
                
                // Если близко к лимиту - предупреждение
                if ($count > $this->maxConnections * 0.9) {
                    $this->log("WARNING: Approaching max connections limit!", true);
                }
            });

            $interval = 30; // Увеличено с 15 до 30 сек для снижения нагрузки
            $loop->addPeriodicTimer($interval, function () {
                $now = time();
                $closed = 0;
                $errors = 0;
                
                foreach ($this->clients as $client) {
                    try {
                        // Закрываем по реальному idle-таймауту
                        $idle = $now - (isset($client->lastPong) ? $client->lastPong : 0);
                        if ($idle >= $this->idleCloseSeconds) {
                            $this->log("Closing idle client ({$idle}s without pong)");
                            try { 
                                $client->close(1000, 'heartbeat timeout'); 
                                $closed++;
                            } catch (\Throwable $e) {
                                $errors++;
                            }
                            continue;
                        }

                        // Закрываем соединения старше 12 часов (защита от утечек памяти)
                        $lifetime = $now - (isset($client->connectionTime) ? $client->connectionTime : $now);
                        if ($lifetime > 43200) { // 12 часов
                            $this->log("Closing old connection ({$lifetime}s lifetime)");
                            try { 
                                $client->close(1000, 'connection too old'); 
                                $closed++;
                            } catch (\Throwable $e) {
                                $errors++;
                            }
                            continue;
                        }

                        // Отправляем только WS-ping (браузер авто-ответит pong)
                        // Убираем app-уровень ping для снижения нагрузки
                        try {
                            $client->send(new Frame('', true, Frame::OP_PING));
                        } catch (\Throwable $e) {
                            $this->log("Ping failed, closing connection");
                            try { 
                                $client->close(1011, 'send failed'); 
                                $closed++;
                            } catch (\Throwable $e2) {
                                $errors++;
                            }
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->log("Error in heartbeat loop: " . $e->getMessage());
                    }
                }
                
                if ($closed > 0 || $errors > 0) {
                    $this->log("Heartbeat: closed={$closed}, errors={$errors}, active=" . count($this->clients), true);
                }
            });
            
            // Сборщик мусора каждые 5 минут
            $loop->addPeriodicTimer(300, function () {
                gc_collect_cycles();
                $mem = round(memory_get_usage(true) / 1024 / 1024, 2);
                $this->log("Memory usage: {$mem} MB", true);
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
        $request = json_decode($msg, true);
        $result = ['message' => ''];

        if (!empty($client->user) && !empty($request['chat'])) {
           $ticket = Support::findByNumber($request['chat']);
           if (Yii::$app->user->can(Role::ROLE_ADMIN) || Yii::$app->user->can(Role::ROLE_MODERATOR) || $ticket->user_id == $client->user->id) {
               $client->chat = $request['chat'];
           }
        }

        $client->send( json_encode($result) );
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
              if (empty($model->user->server)) {
                  $client->send(json_encode([
                                                'type' => 'store.take',
                                                'code' => 500,
                                                'message' => Yii::t('common', "Мы не нашли вас на сервере!", [], $client->user->current_language),
                                                'id' => $model->id,
                                            ]));
                  return;
              }
              if (DropBlocked::getBlocked($model->drop_id, $model->user->server->id)) {
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
                  $client->send(json_encode([
                                                'type' => 'store.take',
                                                'code' => 500,
                                                'message' => Yii::t('common', "Нельзя выполнять действия слишком часто! Подождите 30 секунд.", [], $client->user->current_language),
                                                'id' => $model->id,
                                            ]));
                  return;
              }
              Yii::$app->cache->set($cacheKey, $count + 1, 30);

              if (Yii::$app->user->can(Role::ROLE_ADMIN) || Yii::$app->user->can(Role::ROLE_MODERATOR) || $model->user_id == $client->user->id) {
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
                          $client->send(json_encode([
                                                        'type' => 'store.take',
                                                        'code' => 500,
                                                        'message' => Yii::t('common', "Произошла ошибка, попробуйте позже!", [], $client->user->current_language),
                                                        'id' => $model->id,
                                                    ]));
                          return;
                      }
                      $data = json_decode(json_decode($response, 1)['result'], 1);
                      if (!isset($data['success'])) {
                          $client->send(json_encode([
                                                        'type' => 'store.take',
                                                        'code' => 500,
                                                        'message' => Yii::t('common', "Произошла ошибка, попробуйте позже!", [], $client->user->current_language),
                                                        'id' => $model->id,
                                                    ]));
                          return;
                      }
                      if (!$data['success']) {
                          $client->send(json_encode([
                                                        'type' => 'store.take',
                                                        'code' => 500,
                                                        'message' => $data['error'],
                                                        'id' => $model->id,
                                                    ]));
                          return;
                      }
                      if ($data['success']) {
                          $client->send(json_encode([
                                                        'type' => 'store.take',
                                                        'code' => 200,
                                                        'message' => Yii::t('common', "Товар успешно получен!", [], $client->user->current_language),
                                                        'id' => $model->id,
                                                    ]));
                          return;
                      }
                  } catch (\Exception $e) {
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
          echo "commandGetDrop:" . $e->getLine() . ":" . $e->getMessage() . PHP_EOL;
      }
    }

    public function commandSupportStatus(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        if (!empty($request['id'])) {
            foreach ($this->clients as $chatClient) {
                if (empty($chatClient) || empty($chatClient->chat)) {
                    continue;
                }
                if ($chatClient->chat != $request['id']) {
                    continue;
                }
                $model = new Support();
                $chatClient->send(json_encode(['type' => 'redirect', 'url' => $model->getUrl()]));
            }
        }
    }

    public function commandTicketUpdate(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        if (!empty($request['user_id'])) {
            foreach ($this->clients as $chatClient) {
                if (empty($chatClient) || empty($chatClient->chat)) {
                    continue;
                }
                /** @var User $user */
                $user = $chatClient->user;
                if ($user->id != $request['user_id'] && !$user->canRoles([Role::ROLE_ADMIN, Role::ROLE_MODERATOR])) {
                    continue;
                }
                $chatClient->send(json_encode(['type' => 'ticketsUpdate']));
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
        if (!empty($request['user_id'])) {
            foreach ($this->clients as $chatClient) {
                if (empty($chatClient) || empty($chatClient->chat)) {
                    continue;
                }
                if ($chatClient->chat != $request['id']) {
                    continue;
                }
                $chatClient->send(json_encode(['type' => 'chat', 'messageId' => $request['messageId']]));
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
            foreach ($this->clients as $chatClient) {
                if (empty($chatClient->user)) {
                    continue;
                }
                if ($chatClient->user->id == $model->user->id) {
                    if ($request['code'] == 200) {
                        try {
                            $chatClient->send(
                                json_encode(
                                    [
                                        'type'    => 'store.buy.items',
                                        'code'    => 200,
                                        'id'      => $model->id,
                                        'product'      => Yii::$app->view->renderFile(Yii::getAlias('@frontend/views/store') . '/_product.php', [
                                            'drop' => $model->drop[0],
                                            'serverId' => $model->user->server_id,
                                            'userDrop' => $model,
                                        ]),
                                    ]
                                )
                            );
                        } catch (\Exception $e) {
                            print_r($e->getFile() . ":" . $e->getLine() . PHP_EOL . $e->getMessage());
                        }
                    }
                }
            }
        }
        $client->send( json_encode($result) );
    }

    public function commandActivatedDrop(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        $result = ['message' => ''];

        if (!empty($request['id'])) {
           $model = UserDrop::findOne($request['id']);
           foreach ($this->clients as $chatClient) {
               if (empty($chatClient->user)) {
                   continue;
               }
               if ($chatClient->user->id == $model->user->id) {
                   if ($request['code'] == 200) {
                       $chatClient->send(
                           json_encode(
                               [
                                   'type'    => 'store.get.items',
                                   'code'    => 200,
                                   'message' => Yii::t(
                                       'common',
                                       "Товар успешно получен!",
                                       [],
                                       $chatClient->user->current_language
                                   ),
                                   'id'      => $request['id'],
                               ]
                           )
                       );
                   } else {
                       $chatClient->send(
                           json_encode(
                               [
                                   'type'    => 'store.get.items',
                                   'code'    => 500,
                                   'message' => $request['message'],
                                   'id'      => $request['id'],
                               ]
                           )
                       );
                   }
               }
           }
        }

        $client->send( json_encode($result) );
    }

    public function commandUpdatedBalance(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        $result = ['message' => ''];

        if (!empty($request['user_id'])) {
           $hash = md5(time());
           foreach ($this->clients as $chatClient) {
               if (empty($chatClient->user)) {
                   continue;
               }
               if ($chatClient->user->id == $request['user_id']) {
                   if ($request['code'] == 200) {
                       $chatClient->send(
                           json_encode(
                               [
                                   'type'    => 'update.balance',
                                   'code'    => 200,
                                   'balanceStr'    => $request['balanceStr'],
                                   'balance'    => $request['balance'],
                                   'hash'    => $hash,
                               ]
                           )
                       );
                   }
               }
           }
        }

        $client->send( json_encode($result) );
    }

    public function commandUpdatedOnline(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        $result = ['message' => ''];

        foreach ($this->clients as $chatClient) {
            if ($request['code'] == 200) {
                $chatClient->send(
                    json_encode(
                        [
                            'type'      => 'update.online',
                            'code'      => 200,
                            'servers'    => $request['servers'],
                            'total' => $request['total'],
                        ]
                    )
                );
            }
        }

        $client->send( json_encode($result) );
    }

    public function commandChatFocus(ConnectionInterface $client, $msg)
    {
        $result = ['message' => ''];

        $request = json_decode($msg, true);
        if (empty($client->chat)) {
            return; // Не отправляем пустой ответ
        }
        try {
            if (!empty($client->user) && !empty($request['chatId'])) {
                $message = json_encode([
                    'type' => 'chatFocus',
                    'content' => "Пользователь {$client->user->username} печатает сообщение...",
                ]);
                
                // Оптимизированная рассылка: отправляем только нужным клиентам
                foreach ($this->clients as $chatClient) {
                    if (empty($chatClient) || empty($chatClient->chat)) {
                        continue;
                    }
                    if ($chatClient->chat != $request['chatId'] || $client->user->id === $chatClient->user->id) {
                        continue;
                    }
                    try {
                        $chatClient->send($message);
                    } catch (\Throwable $e) {
                        // Игнорируем ошибки отправки
                    }
                }
            }
        } catch (\Exception $e) {
            $this->log("commandChatFocus error: " . $e->getMessage());
        }
    }

    public function commandChatBlur(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        if (empty($client->chat) || empty($client->user) || empty($request['chatId'])) {
            return; // Не отправляем пустой ответ
        }
        
        try {
            $message = json_encode(['type' => 'chatBlur']);
            
            // Оптимизированная рассылка: отправляем только нужным клиентам
            foreach ($this->clients as $chatClient) {
                if (empty($chatClient) || empty($chatClient->chat)) {
                    continue;
                }
                if ($chatClient->chat != $request['chatId'] || $client->user->id === $chatClient->user->id) {
                    continue;
                }
                try {
                    $chatClient->send($message);
                } catch (\Throwable $e) {
                    // Игнорируем ошибки отправки
                }
            }
        } catch (\Exception $e) {
            $this->log("commandChatBlur error: " . $e->getMessage());
        }
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
                $client->send( json_encode($result) );
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

    public function commandAuth(ConnectionInterface $client, $msg)
    {
        try {
            $request = json_decode($msg, true);
            $result = [];

            if (!empty($request['token']) && !empty($request['steam_id'])) {
                $user = User::findByJwtToken($request['token']);
                if (isset($request['launcher'])) {
                    $client->launcher = $request['launcher'];
                }
                if (!empty($user) && $user->steam_id == $request['steam_id']) {
                    $client->user = $user;
                } else {
                    $result['message'] = 'Invalid token';
                    $client->send( json_encode($result) );
                }
            } else {
                $result['message'] = 'Invalid token';
                $client->send( json_encode($result) );
            }
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('commandAuth: ' . $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage());
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