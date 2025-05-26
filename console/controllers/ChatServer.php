<?php
namespace console\controllers;

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

class ChatServer extends WebSocketServer
{

    public function init()
    {
        parent::init();

        $this->on(self::EVENT_CLIENT_CONNECTED, function(WSClientEvent $e) {
            $e->client->user = null;
            $e->client->сhat = null;
        });
        $this->on(self::EVENT_CLIENT_DISCONNECTED, function(WSClientEvent $e) {
            foreach ($this->clients as $chatClient) {
                if (empty($chatClient->chat) || empty($chatClient->user) || empty($e->client->user)) {
                    continue;
                }
                if ($chatClient->chat !== $e->client->chat || $e->client->user->id === $chatClient->user->id) {
                    continue;
                }
                $chatClient->send(json_encode([
                                                  'type' => 'chatBlur',
                                              ]));
            }
        });
    }

    protected function getCommand(ConnectionInterface $from, $msg)
    {
        $request = json_decode($msg, true);
        return !empty($request['action']) ? $request['action'] : parent::getCommand($from, $msg);
    }

//    private function mime2ext($mime) {
//        $mime_map = [
//            'application/x-compressed'                                                  => '7zip',
//            'video/x-f4v'                                                               => 'f4v',
//            'video/x-flv'                                                               => 'flv',
//            'image/gif'                                                                 => 'gif',
//            'application/x-gtar'                                                        => 'gtar',
//            'application/x-gzip'                                                        => 'gzip',
//            'image/jp2'                                                                 => 'jp2',
//            'video/mj2'                                                                 => 'jp2',
//            'image/jpx'                                                                 => 'jp2',
//            'image/jpm'                                                                 => 'jp2',
//            'image/png'                                                                => 'png',
//            'image/jpeg'                                                                => 'jpeg',
//            'image/pjpeg'                                                               => 'jpeg',
//            'video/quicktime'                                                           => 'mov',
//            'video/x-sgi-movie'                                                         => 'movie',
//            'audio/mpeg'                                                                => 'mp3',
//            'audio/mpg'                                                                 => 'mp3',
//            'audio/mpeg3'                                                               => 'mp3',
//            'audio/mp3'                                                                 => 'mp3',
//            'video/mp4'                                                                 => 'mp4',
//            'video/mpeg'                                                                => 'mpeg',
//            'application/x-photoshop'                                                   => 'psd',
//            'image/vnd.adobe.photoshop'                                                 => 'psd',
//            'application/x-rar'                                                         => 'rar',
//            'application/rar'                                                           => 'rar',
//            'application/x-rar-compressed'                                              => 'rar',
//            'image/svg+xml'                                                             => 'svg',
//            'audio/x-wav'                                                               => 'wav',
//            'audio/wave'                                                                => 'wav',
//            'audio/wav'                                                                 => 'wav',
//            'video/webm'                                                                => 'webm',
//            'image/webp'                                                                => 'webp',
//            'video/x-ms-wmv'                                                            => 'wmv',
//            'video/x-ms-asf'                                                            => 'wmv',
//            'application/x-zip'                                                         => 'zip',
//            'application/zip'                                                           => 'zip',
//            'application/x-zip-compressed'                                              => 'zip',
//            'application/s-compressed'                                                  => 'zip',
//            'multipart/x-zip'                                                           => 'zip',
//        ];
//
//        return isset($mime_map[$mime]) ? $mime_map[$mime] : false;
//    }
//
//    public function commandChatFile(ConnectionInterface $client, $msg)
//    {
//        try {
//            $cacheKey = 'commandChatFile_' . $client->user->id;
//            if (!empty(Yii::$app->cache->get($cacheKey))) {
//                $client->send(json_encode(['type' => 'error', 'error' => Yii::$app->cache->get($cacheKey)]));
//                return;
//            }
//            Yii::$app->cache->set($cacheKey, Yii::t('common', "Нельзя отправлять сообщения слишком часто!", [], $client->user->current_language), 5);
//            $request = json_decode($msg, true);
//            $mimeType = $request['type'];
//            $exp = $this->mime2ext($mimeType);
//            if (empty($exp)) {
//                $client->send(json_encode(['type' => 'error', 'error' => Yii::t('common', "Не верный формат файла!", [], $client->user->current_language)]));
//                return;
//            }
//            $decodedData = file_get_contents($request['data']);
//            $newFileName = $request['chatId'] . "_" . md5(time()) . ".{$exp}";
//            Yii::$app->s3Api->uploadFile('support/' . $newFileName, $decodedData);
//            $chat = Support::findByNumber($request['chatId']);
//            if (empty($chat)) {
//                $chat = new Support();
//                $chat->user_id = $client->user->id;
//                $chat->status = Support::STATUS_OPEN;
//                $chat->server_tag = !empty($user->server_id) ? $user->server->tag : null;
//                $chat->created_at = date('Y-m-d H:i:s');
//                $chat->updated_at = date('Y-m-d H:i:s');
//                $chat->save(false);
//                $mModel = new SupportMessage();
//                $mModel->user_id = null;
//                $mModel->message = "{USER_INFO}";
//                $mModel->support_id = $chat->id;
//                $mModel->created_at = date('Y-m-d H:i:s');
//                $mModel->save();
//                $client->send(json_encode(['type' => 'redirect', 'url' => $chat->getUrl()]));
//            } else {
//                $chat->updated_at = date('Y-m-d H:i:s');
//                $chat->save(false);
//            }
//            $message = new SupportMessage();
//            $message->user_id = $client->user->id;
//            $message->message = null;
//            $message->support_id = $chat->id;
//            $message->created_at = date('Y-m-d H:i:s');
//            $message->save();
//            if (!empty($message->getErrors())) {
//                print_r($message->getErrors());
//            }
//            $filename = htmlspecialchars(\yii\helpers\HtmlPurifier::process($request['filename']));
//            $file = new SupportFile();
//            $file->support_message_id = $message->id;
//            $file->file = $newFileName;
//            $file->filename = $filename;
//            $file->mimetype = $mimeType;
//            $file->created_at = date('Y-m-d H:i:s');
//            $file->save();
//            $this->commandTicketUpdate($client, json_encode(['user_id' => $chat->user_id]));
//            if (!empty($file->getErrors())) {
//                print_r($file->getErrors());
//            }
//            $user = $client->user;
//            foreach ($this->clients as $chatClient) {
//                if (empty($chatClient) || empty($chatClient->chat)) {
//                    continue;
//                }
//                if ($chatClient->chat != $request['chatId']) {
//                    continue;
//                }
//                $chatClient->send(json_encode([
//                                                  'type' => 'chat',
//                                                  'messageId' => $message->id,
//                                              ]));
//            }
//        } catch (\Exception $e) {
//            echo "commandChatFile: " . $e->getMessage() . PHP_EOL;
//        }
//    }

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
           if ($model->drop[0]->blocked($model->user->server->id)) {
               $client->send(json_encode([
                    'type' => 'store.take',
                    'code' => 500,
                    'message' => Yii::t('common', "Товар в вайп-блоке!", [], $client->user->current_language),
                    'id' => $model->id,
               ]));
               return;
           }
           if (Yii::$app->user->can(Role::ROLE_ADMIN) || Yii::$app->user->can(Role::ROLE_MODERATOR) || $model->user_id == $client->user->id) {
                $command = "store.take {$model->user->steam_id} {$model->id}";
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
                   Yii::$app->telegramChats->sendMessage($e->getFile() . ":" . $e->getLine() . "; " . $e->getMessage());
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
                    Yii::$app->cache->set($cacheKey, Yii::t('common', "Нельзя отправлять сообщения слишком часто!", [], $client->user->current_language), 3);
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
                $message = htmlspecialchars(\yii\helpers\HtmlPurifier::process(trim($message)));
                $model = new SupportMessage();
                $model->user_id = $user->id;
                $model->message = trim($message);
                $model->support_id = $chat->id;
                $model->created_at = date('Y-m-d H:i:s');
                $model->save();

                $domain = Yii::$app->settings->get('site_domain');
                $text = "💬 Новое сообщение.";
                $text .= PHP_EOL. "Имя: {$user->username}";
                $text .= PHP_EOL. "Сообщение: {$model->message}";
                $text .= PHP_EOL. "<a href=\"https://{$domain}/support/ticket?id={$chat->getNumber()}\">Перейти к тикету</a>";
                Yii::$app->telegramSupport->sendMessage($text);

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
        } catch (\Exception $e) {
            echo "commandChat:" . $e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage() . PHP_EOL;
        }
    }

    public function commandAuth(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        $result = ['message' => 'Username updated'];

        if (!empty($request['token']) && !empty($request['steam_id'])) {
            $user = User::findByJwtToken($request['token']);
            if (!empty($user) && $user->steam_id == $request['steam_id']) {
                $client->user = $user;
            } else {
                $result['message'] = 'Invalid token';
            }
        } else {
            $result['message'] = 'Invalid token';
        }

        $client->send( json_encode($result) );
    }

}