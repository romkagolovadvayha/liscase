<?php

namespace common\components\queue\stats;

use common\components\queue\telegram\SendMessageJob;
use common\models\servers\Servers;
use common\models\statistics\Chats;
use common\models\teams\Teams;
use common\models\user\User;
use common\models\user\UserRaid;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class SaveChatsJob extends BaseObject implements JobInterface
{
    public $messages;
    public $serverTag;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            foreach ($this->messages as $item) {
                $model = new Chats();
                $model->steam_id = $item['steam_id'];
                $model->message = $item['message'];
                $model->created_at = $item['created_at'];
                $model->server_tag = $this->serverTag;
                $model->save();
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage($this->data);
            Yii::$app->telegramChats->sendMessage("SaveChatsJob: " . $e->getFile() . ":" . $e->getLine() . ": " . $e->getMessage());
        }
    }
}