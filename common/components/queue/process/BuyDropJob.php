<?php

namespace common\components\queue\process;

use common\models\map\Map;
use common\models\servers\Servers;
use common\models\user\UserDrop;
use WebSocket\Client;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class BuyDropJob extends BaseObject implements JobInterface
{
    /**
     * @var UserDrop
     */
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
            // Используем кеш вместо WebSocket клиента
            \console\controllers\ChatServer::broadcastBuyDrop($userDrop->id, $userDrop->user_id);
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('BuyDropJob: ' . $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage());
        }
    }

}