<?php

namespace common\components\queue\stats;

use common\components\queue\telegram\SendMessageJob;
use common\models\servers\Servers;
use common\models\teams\Teams;
use common\models\user\User;
use common\models\user\UserRaid;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class SaveTeamsJob extends BaseObject implements JobInterface
{
    public $data;
    public $ip;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        Yii::$app->telegramChats->sendMessage($this->data);
        try {
            $request = json_decode($this->data, 1);

            if ($this->ip != $request['ip']) {
                return;
            }

            /** @var Servers $server */
            $server = Servers::find()
                             ->cache(60)
                             ->andWhere(['ip' => $request['ip']])
                             ->andWhere(['port' => $request['port']])
                             ->one();
            if (empty($server)) {
                return;
            }
            if (!empty($request['teams'])) {
                foreach ($request['teams'] as $item) {
                    try {
                        Teams::updateTeam($item['LeaderSteamId'], $item['Members'], $server->id, $server->currentWipe());
                    } catch (\Exception $e) {
                        Yii::$app->telegramChats->sendMessage($this->data);
                        Yii::$app->telegramChats->sendMessage("SaveTeamsJob: " . $e->getFile() . ":" . $e->getLine() . ": " . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage($this->data);
            Yii::$app->telegramChats->sendMessage("SaveTeamsJob: " . $e->getFile() . ":" . $e->getLine() . ": " . $e->getMessage());
        }
    }
}