<?php

namespace common\components\queue\process;

use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class ReturnDropJob extends BaseObject implements JobInterface
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
            
            $data = [
                'type'    => 'store.return.item',
                'code'    => 200,
                'message' => Yii::t('common', "Предмет успешно возвращен!", [], $userDrop->user->current_language),
                'id'      => $userDrop->id,
                'timestamp' => time(),
            ];
            
            // Сохраняем в кеш для отправки через WebSocket таймер
            $cacheKey = 'ws_return_drop_' . $userDrop->user_id . '_' . $userDrop->id;
            Yii::$app->cache->set($cacheKey, $data, 30);
        } catch (\Exception $e) {
            Yii::error('ReturnDropJob error: ' . $e->getMessage());
        }
    }
}

