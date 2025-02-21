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

class UpdateStatsUserJob extends BaseObject implements JobInterface
{
    public $steam_id;
    public $params;
    public $serverTag;
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
            $steamId = $this->steam_id;
            $wipeDate = $this->wipeDate;
            $params = $this->params;
            $statistics = Statistics::find()
                                    ->andWhere(['steam_id' => $steamId])
                                    ->andWhere(['server_tag' => $this->serverTag])
                                    ->andWhere(['wipe' => $wipeDate])
                                    ->indexBy('key')
                                    ->all();

            unset($params['kills']);
            unset($params['deaths']);
            foreach ($params as $key => $value) {
                if (empty($value)) {
                    continue;
                }
                if (!empty($statistics[$key])) {
                    $statistics[$key]->value += $value;
                    $statistics[$key]->save();
                } else {
                    $model = new Statistics();
                    $model->steam_id = $steamId;
                    $model->server_tag = $this->serverTag;
                    $model->key = $key;
                    $model->value = $value;
                    $model->wipe = $wipeDate;
                    $model->save();
                }
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("UpdateTopJob::updateTop(user, key, value, server, wipeDate): " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }
}