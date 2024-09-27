<?php

namespace common\components\queue\telegram;

use common\components\oauth\Steam;
use common\models\servers\Servers;
use common\models\statistics\Chats;
use common\models\statistics\Kills;
use common\models\statistics\Reports;
use common\models\statistics\Statistics;
use common\models\statistics\Teams;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class SendPhotoJob extends BaseObject implements JobInterface
{
    public $telegram_chat_id;
    public $photo;
    public $message;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            Yii::$app->personalBotTelegram->sendPhoto($this->telegram_chat_id, $this->photo, $this->message);
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("SendPhotoJob: " . $e->getMessage());

        }
    }
}