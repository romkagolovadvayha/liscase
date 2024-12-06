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

class UpdateTeamJob extends BaseObject implements JobInterface
{
    public $model;
    public $server;
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
            Team::updateTeam($this->model, $this->server, $this->wipeDate);
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("UpdateTeamJob::updateTeam: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }
}