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
            
            // Сохраняем в кеш для отправки через WebSocket таймер
            $cacheKey = 'ws_buy_drop_' . $userDrop->user_id . '_' . time() . '_' . $userDrop->id;
            Yii::$app->cache->set($cacheKey, [
                'action' => 'buyDrop',
                'code' => 200,
                'id' => $userDrop->id,
                'user_id' => $userDrop->user_id,
                'timestamp' => time(),
            ], 30);
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('BuyDropJob: ' . $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage());
        }
    }

}