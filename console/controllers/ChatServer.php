<?php
namespace console\controllers;

use common\models\support\SupportFile;
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

    function mime2ext($mime) {
        $mime_map = [
            'application/x-compressed'                                                  => '7zip',
            'video/x-f4v'                                                               => 'f4v',
            'video/x-flv'                                                               => 'flv',
            'image/gif'                                                                 => 'gif',
            'application/x-gtar'                                                        => 'gtar',
            'application/x-gzip'                                                        => 'gzip',
            'image/jp2'                                                                 => 'jp2',
            'video/mj2'                                                                 => 'jp2',
            'image/jpx'                                                                 => 'jp2',
            'image/jpm'                                                                 => 'jp2',
            'image/png'                                                                => 'png',
            'image/jpeg'                                                                => 'jpeg',
            'image/pjpeg'                                                               => 'jpeg',
            'video/quicktime'                                                           => 'mov',
            'video/x-sgi-movie'                                                         => 'movie',
            'audio/mpeg'                                                                => 'mp3',
            'audio/mpg'                                                                 => 'mp3',
            'audio/mpeg3'                                                               => 'mp3',
            'audio/mp3'                                                                 => 'mp3',
            'video/mp4'                                                                 => 'mp4',
            'video/mpeg'                                                                => 'mpeg',
            'application/x-photoshop'                                                   => 'psd',
            'image/vnd.adobe.photoshop'                                                 => 'psd',
            'application/x-rar'                                                         => 'rar',
            'application/rar'                                                           => 'rar',
            'application/x-rar-compressed'                                              => 'rar',
            'image/svg+xml'                                                             => 'svg',
            'audio/x-wav'                                                               => 'wav',
            'audio/wave'                                                                => 'wav',
            'audio/wav'                                                                 => 'wav',
            'video/webm'                                                                => 'webm',
            'image/webp'                                                                => 'webp',
            'video/x-ms-wmv'                                                            => 'wmv',
            'video/x-ms-asf'                                                            => 'wmv',
            'application/x-zip'                                                         => 'zip',
            'application/zip'                                                           => 'zip',
            'application/x-zip-compressed'                                              => 'zip',
            'application/s-compressed'                                                  => 'zip',
            'multipart/x-zip'                                                           => 'zip',
        ];

        return isset($mime_map[$mime]) ? $mime_map[$mime] : false;
    }

    public function commandChatFile(ConnectionInterface $client, $msg)
    {
        try {
            $request = json_decode($msg, true);
            $mimeType = $request['type'];
            $exp = $this->mime2ext($mimeType);
            if (empty($exp)) {
                return;
            }
            $decodedData = file_get_contents($request['data']);
            $uploadDir = Yii::getAlias('@frontend/web/uploads');
            $newFileName = $request['chatId'] . "_" . md5(time()) . ".{$exp}";
            $filePath = $uploadDir . "/chat/" . $newFileName;
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath));
                chmod(dirname($filePath), 0777);
            }
            file_put_contents($filePath, $decodedData);
            $chat = Support::findByNumber($request['chatId']);
            $message = new SupportMessage();
            $message->user_id = $client->user->id;
            $message->message = null;
            $message->support_id = $chat->id;
            $message->created_at = date('Y-m-d H:i:s');
            $message->save();
            if (!empty($message->getErrors())) {
                print_r($message->getErrors());
            }
            $filename = htmlspecialchars(\yii\helpers\HtmlPurifier::process($request['filename']));
            $file = new SupportFile();
            $file->support_message_id = $message->id;
            $file->file = $newFileName;
            $file->filename = $filename;
            $file->mimetype = $mimeType;
            $file->created_at = date('Y-m-d H:i:s');
            $file->save();
            if (!empty($file->getErrors())) {
                print_r($file->getErrors());
            }
            $user = $client->user;
            foreach ($this->clients as $chatClient) {
                if (empty($chatClient) || empty($chatClient->chat)) {
                    continue;
                }
                if ($chatClient->chat != $request['chatId']) {
                    continue;
                }
                $chatClient->send(json_encode([
                                                  'type' => 'chat',
                                                  'messageId' => $message->id,
                                              ]));
            }
        } catch (\Exception $e) {
            echo "commandChatFile: " . $e->getMessage() . PHP_EOL;
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
            }

            if (!empty($client->user) && !empty($request['message']) && $message = trim($request['message']) ) {
                /** @var User $user */
                $user = $client->user;
                $chat = Support::findByNumber($client->chat);
                $message = htmlspecialchars(\yii\helpers\HtmlPurifier::process($message));
                $model = new SupportMessage();
                $model->user_id = $user->id;
                $model->message = $message;
                $model->support_id = $chat->id;
                $model->created_at = date('Y-m-d H:i:s');
                $model->save();
                foreach ($this->clients as $chatClient) {
                    if (empty($chatClient) || empty($chatClient->chat)) {
                        continue;
                    }
                    if ($chatClient->chat != $request['chatId']) {
                        continue;
                    }
                    $chatClient->send(json_encode([
                                                      'type' => 'chat',
                                                      'messageId' => $model->id,
                                                  ]));
                }
            } else {
                $result['message'] = 'Enter message';
            }

            $client->send( json_encode($result) );
        } catch (\Exception $e) {
            echo "commandChat:" . $e->getLine() . ":" . $e->getMessage() . PHP_EOL;
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