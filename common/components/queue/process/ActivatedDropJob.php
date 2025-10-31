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
            if ($userDrop->save()) {
                // Используем кеш вместо WebSocket клиента
                \console\controllers\ChatServer::broadcastActivatedDrop($userDrop->id, $userDrop->user_id, 200);
            } else {
                // Используем кеш вместо WebSocket клиента
                $errorMessage = Yii::t(
                    'common',
                    "Произошла ошибка при получении товара, попробуйте позже!",
                    [],
                    $userDrop->user->current_language
                );
                \console\controllers\ChatServer::broadcastActivatedDrop($userDrop->id, $userDrop->user_id, 500, $errorMessage);
            }
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('ApiController: ' . $ex->getMessage());
        }
    }

}