<?php

namespace common\components\queue\process;

use common\models\map\MapList;
use common\models\servers\Servers;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class MapFixJob extends BaseObject implements JobInterface
{
    public $serverId;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            /** @var Servers $server */
            $server = Servers::findOne($this->serverId);
            
            if (!$server) {
                Yii::error("MapFixJob: Server not found with id {$this->serverId}", __METHOD__);
                return;
            }
            
            // Фиксируем карту с наибольшим количеством голосов
            MapList::fixWinningMapForServer($this->serverId);
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("MapFixJob: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }
}

