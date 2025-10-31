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
    private function log($m) {
        echo date('Y-m-d H:i:s') . " [WS] {$m}" . PHP_EOL;
    }

    public function init()
    {
        parent::init();

        $this->on(self::EVENT_CLIENT_CONNECTED, function(WSClientEvent $e) {
            try {
                $this->log("New client connected. Total clients: " . count($this->clients));
                $e->client->user = null;
                $e->client->chat = null;
                $e->client->launcher = false;
                $e->client->lastPong = time(); // Время последней активности
            } catch (\Throwable $ex) {
                $this->log("ERROR in EVENT_CLIENT_CONNECTED: " . $ex->getMessage() . " at " . $ex->getFile() . ":" . $ex->getLine());
                Yii::$app->telegramChats->sendMessage('ChatServer EVENT_CLIENT_CONNECTED error: ' . $ex->getMessage());
            }
        });
        
        $this->on(self::EVENT_CLIENT_DISCONNECTED, function(WSClientEvent $e) {
            try {
                $userId = !empty($e->client->user) ? $e->client->user->id : 'guest';
                $this->log("Client disconnected (user: {$userId}). Total clients: " . count($this->clients));
                
                foreach ($this->clients as $chatClient) {
                    if (empty($chatClient->chat) || empty($chatClient->user) || empty($e->client->user)) {
                        continue;
                    }
                    if ($chatClient->chat !== $e->client->chat || $e->client->user->id === $chatClient->user->id) {
                        continue;
                    }
                    try {
                        $chatClient->send(json_encode(['type' => 'chatBlur']));
                    } catch (\Throwable $ex) {
                        $this->log("ERROR sending chatBlur: " . $ex->getMessage());
                    }
                }
            } catch (\Throwable $ex) {
                $this->log("ERROR in EVENT_CLIENT_DISCONNECTED: " . $ex->getMessage() . " at " . $ex->getFile() . ":" . $ex->getLine());
                try {
                    Yii::$app->telegramChats->sendMessage('ChatServer EVENT_CLIENT_DISCONNECTED error: ' . $ex->getMessage());
                } catch (\Throwable $telegramEx) {
                    // Ignore telegram errors
                }
            }
        });

        // После старта сокета есть loop
        $this->on(self::EVENT_WEBSOCKET_OPEN, function () {
            /** @var \Ratchet\Server\IoServer $io */
            $io = $this->server;
            $loop = $io->loop;

            // Одноразовый вывод через 60 сек после старта — сколько соединений
            $loop->addTimer(60, function () {
                $memoryUsage = round(memory_get_usage() / 1024 / 1024, 2);
                $memoryPeak = round(memory_get_peak_usage() / 1024 / 1024, 2);
                $this->log("Server stats after 60s: clients=" . count($this->clients) . ", memory={$memoryUsage}MB, peak={$memoryPeak}MB");
            });
            
            // Периодическая статистика каждые 5 минут
            $loop->addPeriodicTimer(300, function () {
                $memoryUsage = round(memory_get_usage() / 1024 / 1024, 2);
                $memoryPeak = round(memory_get_peak_usage() / 1024 / 1024, 2);
                $this->log("Server stats: clients=" . count($this->clients) . ", memory={$memoryUsage}MB, peak={$memoryPeak}MB");
                
                // Предупреждение если память больше 500MB
                if ($memoryUsage > 500) {
                    $this->log("WARNING: High memory usage detected!");
                    try {
                        Yii::$app->telegramChats->sendMessage("ChatServer WARNING: High memory usage {$memoryUsage}MB with " . count($this->clients) . " clients");
                    } catch (\Throwable $ex) {
                        // Ignore
                    }
                }
            });

            // Упрощённый heartbeat - только очистка мёртвых соединений
            $interval = 30; // каждые 30 сек проверяем
            $loop->addPeriodicTimer($interval, function () {
                try {
                    $now = time();
                    $totalClients = count($this->clients);
                    $closedCount = 0;
                    $errorCount = 0;
                    
                    foreach ($this->clients as $client) {
                        try {
                            // Закрываем только если давно не было активности (90 секунд)
                            $idle = $now - (isset($client->lastPong) ? $client->lastPong : 0);
                            if ($idle >= 90) {
                                try { 
                                    $client->close(1000, 'idle timeout'); 
                                    $closedCount++;
                                } catch (\Throwable $e) {
                                    $errorCount++;
                                }
                            }
                        } catch (\Throwable $clientEx) {
                            $errorCount++;
                        }
                    }
                    
                    if ($closedCount > 0 || $errorCount > 0) {
                        $this->log("Cleanup: closed={$closedCount}, errors={$errorCount}, active={$totalClients}");
                    }
                } catch (\Throwable $ex) {
                    $this->log("ERROR in cleanup timer: " . $ex->getMessage());
                    try {
                        Yii::$app->telegramChats->sendMessage('ChatServer cleanup error: ' . $ex->getMessage());
                    } catch (\Throwable $telegramEx) {
                        // Ignore
                    }
                }
            });
        });
    }

    protected function getCommand(ConnectionInterface $from, $msg)
    {
        try {
            // Любое входящее сообщение обновляет lastPong (клиент активен)
            $from->lastPong = time();

            $request = json_decode($msg, true) ?: [];
            $command = !empty($request['action']) ? $request['action'] : parent::getCommand($from, $msg);
            
            // Логируем команды для диагностики
            $userId = !empty($from->user) ? $from->user->id : 'guest';
            $this->log("Command: {$command} from user: {$userId}");
            
            return $command;
            
        } catch (\Throwable $ex) {
            $this->log("ERROR in getCommand: " . $ex->getMessage());
            try {
                Yii::$app->telegramChats->sendMessage('ChatServer getCommand error: ' . $ex->getMessage() . " | msg: " . substr($msg, 0, 100));
            } catch (\Throwable $telegramEx) {
                // Ignore
            }
            return 'error';
        }
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
           $sentCount = 0;
           
           foreach ($this->clients as $chatClient) {
               if (empty($chatClient->user)) {
                   continue;
               }
               if ($chatClient->user->id == $request['user_id']) {
                   if ($request['code'] == 200) {
                       try {
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
                           $sentCount++;
                       } catch (\Throwable $e) {
                           $this->log("Failed to send balance update to user {$request['user_id']}: " . $e->getMessage());
                       }
                   }
               }
           }
           
           if ($sentCount > 0) {
               $this->log("Balance update sent to {$sentCount} client(s) for user {$request['user_id']}");
           }
        }

        // Сразу закрываем подключение от NotifyBalanceJob (это не браузерный клиент)
        try {
            $client->send(json_encode($result));
            $client->close();
        } catch (\Throwable $e) {
            // Ignore
        }
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
            $client->send( json_encode($result) );
            return;
        }
        try {
            if (!empty($client->user)) {
                foreach ($this->clients as $chatClient) {
                    if (empty($chatClient) || empty($chatClient->chat)) {
                        continue;
                    }
                    if ($chatClient->chat != $request['chatId'] || $client->user->id === $chatClient->user->id) {
                        continue;
                    }
                    $chatClient->send(json_encode([
                                                      'type' => 'chatFocus',
                                                      'content' => "Пользователь {$client->user->username} печатает сообщение...",
                                                  ]));
                }
                return;
            }
        } catch (\Exception $e) {
            echo "commandChatFocus:" . $e->getLine() . ":" . $e->getMessage() . PHP_EOL;
        }
        $client->send( json_encode($result) );
    }

    public function commandChatBlur(ConnectionInterface $client, $msg)
    {
        $result = ['message' => ''];

        $request = json_decode($msg, true);
        if (empty($client->chat)) {
            $client->send( json_encode($result) );
            return;
        }
        try {
            if (!empty($client->user)) {
                foreach ($this->clients as $chatClient) {
                    try {
                        if (empty($chatClient) || empty($chatClient->chat)) {
                            continue;
                        }
                    } catch (\Exception $e) {
                        echo "commandChatBlur1:" . $e->getLine() . ":" . $e->getMessage() . PHP_EOL;
                    }
                    if ($chatClient->chat != $request['chatId'] || $client->user->id === $chatClient->user->id) {
                        continue;
                    }
                    $chatClient->send(json_encode([
                                                      'type' => 'chatBlur',
                                                  ]));
                }
                return;
            }
        } catch (\Exception $e) {
            echo "commandChatBlur:" . $e->getLine() . ":" . $e->getMessage() . PHP_EOL;
        }
        $client->send( json_encode($result) );
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
                    // Проверяем количество подключений этого пользователя
                    $userConnectionsCount = 0;
                    foreach ($this->clients as $existingClient) {
                        if (!empty($existingClient->user) && $existingClient->user->id == $user->id) {
                            $userConnectionsCount++;
                        }
                    }
                    
                    // Ограничение: максимум 5 подключений на пользователя
                    if ($userConnectionsCount >= 5) {
                        $this->log("User {$user->id} exceeded connection limit ({$userConnectionsCount})");
                        $result['message'] = 'Too many connections';
                        $client->send(json_encode($result));
                        $client->close();
                        return;
                    }
                    
                    $client->user = $user;
                    $this->log("User {$user->id} authenticated (total connections: " . ($userConnectionsCount + 1) . ")");
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
    /**
     * Обработчик неизвестных команд
     */
    public function commandError(ConnectionInterface $client, $msg)
    {
        try {
            $this->log("Unknown/Error command received: " . substr($msg, 0, 100));
            $client->send(json_encode(['type' => 'error', 'message' => 'Unknown command']));
        } catch (\Throwable $ex) {
            $this->log("ERROR in commandError: " . $ex->getMessage());
        }
    }
    
    /**
     * Глобальный обработчик ошибок для всех команд
     */
    public function __call($name, $arguments)
    {
        try {
            // Если метод не существует
            if (!method_exists($this, $name)) {
                $this->log("WARNING: Command method {$name} does not exist");
                
                if (isset($arguments[0]) && $arguments[0] instanceof ConnectionInterface) {
                    $arguments[0]->send(json_encode(['type' => 'error', 'message' => 'Command not supported']));
                }
                
                return;
            }
            
            return parent::__call($name, $arguments);
        } catch (\Throwable $ex) {
            $this->log("CRITICAL ERROR in __call({$name}): " . $ex->getMessage() . " at " . $ex->getFile() . ":" . $ex->getLine());
            try {
                Yii::$app->telegramChats->sendMessage("ChatServer CRITICAL: {$name} failed: " . $ex->getMessage());
            } catch (\Throwable $telegramEx) {
                // Ignore
            }
        }
    }
}