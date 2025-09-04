<?php

namespace common\components\queue\stats;

use common\components\oauth\Steam;
use common\models\rcon\RconTasks;
use common\models\servers\Servers;
use common\models\statistics\Chats;
use common\models\statistics\Kills;
use common\models\statistics\Reports;
use common\models\statistics\Statistics;
use common\models\statistics\Teams;
use common\models\team\Team;
use common\models\user\User;
use common\models\user\UserTop;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class UpdateTopJob extends BaseObject implements JobInterface
{
    public $userId;
    public $key;
    public $value;
    public $serverId;
    public $wipeDate;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            UserTop::updateTop($this->userId, $this->key, $this->value, $this->serverId, $this->wipeDate);
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("UpdateTopJob::updateTop(user, key, value, server, wipeDate): " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }
}