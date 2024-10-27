<?php
namespace console\controllers;

use common\models\support\SupportMessage;
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

    public function commandChatFocus(ConnectionInterface $client, $msg)
    {
        try {
            $result = ['message' => ''];

            if (empty($client->chat)) {
                $client->send( json_encode($result) );
            }
            if (!empty($client->user)) {
                foreach ($this->clients as $chatClient) {
                    if ($chatClient->chat !== $client->chat || $client->user->id === $chatClient->user->id) {
                        continue;
                    }
                    $chatClient->send(json_encode([
                                                      'type' => 'chatFocus',
                                                      'content' => "Пользователь {$client->user->username} печатает сообщение...",
                                                  ]));
                }
            }
        } catch (\Exception $e) {
            echo "commandChatFocus:" . $e->getLine() . ":" . $e->getMessage() . PHP_EOL;
        }
    }

    public function commandChatBlur(ConnectionInterface $client, $msg)
    {
        try {
            $result = ['message' => ''];

            if (empty($client->chat)) {
                $client->send( json_encode($result) );
            }
            if (!empty($client->user)) {
                foreach ($this->clients as $chatClient) {
                    if ($chatClient->chat !== $client->chat || $client->user->id === $chatClient->user->id) {
                        continue;
                    }
                    $chatClient->send(json_encode([
                                                      'type' => 'chatBlur',
                                                  ]));
                }
            }
        } catch (\Exception $e) {
            echo "commandChatFocus:" . $e->getLine() . ":" . $e->getMessage() . PHP_EOL;
        }
    }

    public function commandChat(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        $result = ['message' => ''];

        if (empty($client->chat)) {
            $client->send( json_encode($result) );
        }

        if (!empty($client->user) && !empty($request['message']) && $message = trim($request['message']) ) {
            /** @var User $user */
            $user = $client->user;
            $usernameClass = '';
            if (Yii::$app->user->can(Role::ROLE_MODERATOR)) {
                $usernameClass = 'moder';
            }
            if (Yii::$app->user->can(Role::ROLE_ADMIN)) {
                $usernameClass = 'admin';
            }
            $chat = Support::findByNumber($client->chat);
            $model = new SupportMessage();
            $model->user_id = $user->id;
            $model->message = $message;
            $model->support_id = $chat->id;
            $model->created_at = date('Y-m-d H:i:s');
            $model->save();
            foreach ($this->clients as $chatClient) {
                if ($chatClient->chat !== $client->chat) {
                    continue;
                }
                $message = htmlspecialchars(\yii\helpers\HtmlPurifier::process($message));
                $chatClient->send(json_encode([
                                                   'type' => 'chat',
                                                   'avatar' => $user->getAvatar(),
                                                   'username' => $user->username,
                                                   'message' => $message,
                                                   'usernameClass' => $usernameClass,
                                               ]));
            }
        } else {
            $result['message'] = 'Enter message';
        }

        $client->send( json_encode($result) );
    }

    public function commandAuth(ConnectionInterface $client, $msg)
    {
        $request = json_decode($msg, true);
        $result = ['message' => 'Username updated'];

        if (!empty($request['token']) && !empty($request['steam_id'])) {
            $user = User::findByJwtToken($request['token']);
            if (!empty($user) && $user->steam_id == $request['steam_id']) {
                $client->user = $user;
                echo $user->id . PHP_EOL;
            } else {
                $result['message'] = 'Invalid token';
            }
        } else {
            $result['message'] = 'Invalid token';
        }

        $client->send( json_encode($result) );
    }

}