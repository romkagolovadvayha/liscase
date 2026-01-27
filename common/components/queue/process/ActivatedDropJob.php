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
                    'type'    => 'store.get.items',
                    'code'    => 200,
                    'message' => Yii::t('common', "Товар успешно получен!", [], $userDrop->user->current_language),
                    'id'      => $userDrop->id,
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
                    'type'    => 'store.get.items',
                    'code'    => 500,
                    'message' => $errorMessage,
                    'id'      => $userDrop->id,
                    'timestamp' => time(),
                ];
            }
            
            // Сохраняем в кеш для отправки через WebSocket таймер
            $cacheKey = 'ws_activated_drop_' . $userDrop->user_id . '_' . $userDrop->id;
            Yii::$app->cache->set($cacheKey, $data, 30);
            
            // Сохраняем список активных дропов для пользователя
            $listKey = 'ws_drops_list_' . $userDrop->user_id;
            $dropsList = Yii::$app->cache->get($listKey) ?: [];
            if (!in_array($userDrop->id, $dropsList)) {
                $dropsList[] = $userDrop->id;
                Yii::$app->cache->set($listKey, $dropsList, 60);
            }
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('ApiController: ' . $ex->getMessage());
        }
    }

}