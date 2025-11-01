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
            // Используем простой ключ с user_id и drop_id
            $cacheKey = 'ws_buy_drop_' . $userDrop->user_id . '_' . $userDrop->id;
            $data = [
                'action' => 'buyDrop',
                'code' => 200,
                'id' => $userDrop->id,
                'user_id' => $userDrop->user_id,
                'timestamp' => time(),
            ];
            
            $result = Yii::$app->cache->set($cacheKey, $data, 30);
            
            // Сохраняем список активных дропов для пользователя
            $listKey = 'ws_drops_list_' . $userDrop->user_id;
            $dropsList = Yii::$app->cache->get($listKey) ?: [];
            if (!in_array($userDrop->id, $dropsList)) {
                $dropsList[] = $userDrop->id;
                Yii::$app->cache->set($listKey, $dropsList, 60);
            }
            
            // Логируем для отладки
            Yii::$app->telegramChats->sendMessage("BuyDropJob: key={$cacheKey}, saved=" . ($result ? 'yes' : 'no') . ", user_id={$userDrop->user_id}, drop_id={$userDrop->id}");
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('BuyDropJob ERROR: ' . $ex->getFile() . ':' . $ex->getLine() . ' ' . $ex->getMessage());
        }
    }

}