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
            
            // Сохраняем событие в кеш для обработки в ChatServer
            // ChatServer имеет доступ к view для рендеринга
            $cacheKey = 'ws_buy_drop_' . $userDrop->user_id . '_' . $userDrop->id;
            $data = [
                'action' => 'buyDrop', // ChatServer обработает через commandBuyDrop
                'code' => 200,
                'id' => $userDrop->id,
                'timestamp' => time(),
            ];
            
            Yii::$app->cache->set($cacheKey, $data, 30);
            
            // Сохраняем список активных дропов для пользователя
            $listKey = 'ws_drops_list_' . $userDrop->user_id;
            $dropsList = Yii::$app->cache->get($listKey) ?: [];
            if (!in_array($userDrop->id, $dropsList)) {
                $dropsList[] = $userDrop->id;
                Yii::$app->cache->set($listKey, $dropsList, 60);
            }
            
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('BuyDropJob ERROR: ' . $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage());
        }
    }

}