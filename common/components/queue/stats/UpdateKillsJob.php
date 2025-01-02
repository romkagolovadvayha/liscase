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

class UpdateKillsJob extends BaseObject implements JobInterface
{
    public $item;
    public $serverTag;
    public $wipeDate;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        $item = $this->item;
        try {
            try {
                User::findBySteamId($item['steam_id']);
                if (strlen($item['dead']) >= 16) {
                    User::findBySteamId($item['dead']);
                }
            } catch (\Exception $ex) {}
            $model = new Kills();
            $model->steam_id = $item['steam_id'];
            $model->type = $item['type'];
            $model->dead = $item['dead'];
            $model->weapon = $item['weapon'];
            $model->distance = $item['distance'];
            $model->created_at = $item['date'];
            $model->server_tag = $this->serverTag;
            $model->wipe = $this->wipeDate;
            $model->save();


            if ($item['type'] == 'kill') {
                /** @var Statistics $paramKills */
                $paramKills = Statistics::find()
                                        ->andWhere(['steam_id' => $model->steam_id])
                                        ->andWhere(['server_tag' => $this->serverTag])
                                        ->andWhere(['wipe' => $this->wipeDate])
                                        ->andWhere(['key' => 'kills'])
                                        ->one();

                if (!empty($paramKills)) {
                    $paramKills->value++;
                    $paramKills->save(false);
                } else {
                    $nModel = new Statistics();
                    $nModel->steam_id = $model->steam_id;
                    $nModel->server_tag = $this->serverTag;
                    $nModel->key = 'kills';
                    $nModel->value = 1;
                    $nModel->wipe = $this->wipeDate;
                    $nModel->save();
                }

                /** @var Statistics $paramDeaths */
                $paramDeaths = Statistics::find()
                                        ->andWhere(['steam_id' => $item['dead']])
                                        ->andWhere(['server_tag' => $this->serverTag])
                                        ->andWhere(['wipe' => $this->wipeDate])
                                        ->andWhere(['key' => 'deaths'])
                                        ->one();
                if (!empty($paramDeaths)) {
                    $paramDeaths->value++;
                    $paramDeaths->save(false);
                } else {
                    $nModel = new Statistics();
                    $nModel->steam_id = $model->steam_id;
                    $nModel->server_tag = $this->serverTag;
                    $nModel->key = 'deaths';
                    $nModel->value = 1;
                    $nModel->wipe = $this->wipeDate;
                    $nModel->save();
                }
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("UpdateKillsJob" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }
}