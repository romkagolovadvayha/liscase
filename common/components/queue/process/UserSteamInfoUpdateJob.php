<?php

namespace common\components\queue\process;

use common\models\user\User;
use WebSocket\Client;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class UserSteamInfoUpdateJob extends BaseObject implements JobInterface
{

    public $steamId;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
           User::findBySteamId($this->steamId, true);
        } catch (\Exception $ex) {
            Yii::$app->telegramChats->sendMessage('UserSteamInfoUpdateJob: ' . $ex->getMessage());
        }
    }

}