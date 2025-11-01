<?php

namespace common\components\queue\process;

use common\models\map\Map;
use common\models\servers\Servers;
use WebSocket\Client;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class ActivatedDropJob extends BaseObject implements JobInterface
{
    public $userDrop;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            $userDrop = $this->userDrop;
            
            // Отправляем через существующее WebSocket подключение
            $chatServer = \console\controllers\ChatServer::getInstance();
            if ($chatServer) {
                // Используем индексы для поиска клиентов пользователя
                $userClients = $chatServer->getClientsByUserId($userDrop->user_id);
                
                if ($userDrop->save()) {
                    $response = json_encode([
                        'action' => 'activatedDrop',
                        'code'   => 200,
                        'id'     => $userDrop->id,
                    ]);
                } else {
                    $errorMessage = Yii::t(
                        'common',
                        "Произошла ошибка при получении товара, попробуйте позже!",
                        [],
                        $userDrop->user->current_language
                    );
                    $response = json_encode([
                        'action' => 'activatedDrop',
                        'code'   => 500,
                        'message' => $errorMessage,
                        'id'     => $userDrop->id,
                    ]);
                }
                
                foreach ($userClients as $client) {
                    try {
                        $client->send($response);
                    } catch (\Exception $ex) {
                        // Клиент отключен, пропускаем
                    }
                }
            }
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('ApiController: ' . $ex->getMessage());
        }
    }

}