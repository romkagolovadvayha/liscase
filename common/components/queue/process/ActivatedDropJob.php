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
                $data = [
                    'action' => 'activatedDrop',
                    'code'   => 200,
                    'id'     => $userDrop->id,
                    'user_id' => $userDrop->user_id,
                    'timestamp' => time(),
                ];
            } else {
                $errorMessage = Yii::t(
                    'common',
                    "Произошла ошибка при получении товара, попробуйте позже!",
                    [],
                    $userDrop->user->current_language
                );
                $data = [
                    'action' => 'activatedDrop',
                    'code'   => 500,
                    'message' => $errorMessage,
                    'id'     => $userDrop->id,
                    'user_id' => $userDrop->user_id,
                    'timestamp' => time(),
                ];
            }
            
            // Сохраняем в кеш для отправки через WebSocket таймер
            $cacheKey = 'ws_activated_drop_' . $userDrop->user_id . '_' . time() . '_' . $userDrop->id;
            Yii::$app->cache->set($cacheKey, $data, 30);
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('ApiController: ' . $ex->getMessage());
        }
    }

}