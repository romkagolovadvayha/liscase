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

class UpdateOnlineJob extends BaseObject implements JobInterface
{
    public $steam_ids;
    public $serverId;
    public $wipeDate;
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
            $data = [];
            $cacheKey = "UpdateOnlineJob_{$this->serverId}_{$this->wipeDate}";
            if (Yii::$app->cache->get($cacheKey)) {
                $data = Yii::$app->cache->get($cacheKey);
            }
            if (empty($data['items'])) {
                $data['items'] = [];
                $data['time'] = time();
            }

            foreach ($this->steam_ids as $steamId) {
                if (empty($data['items'][$steamId])) {
                    $data['items'][$steamId] = 1;
                    continue;
                }
                $data['items'][$steamId]++;
            }

            if (time() - $data['time'] >= 5 * 60) {
                foreach ($data['items'] as $steamId => $playTime) {
                    $user = User::findBySteamId($steamId);
                    $statistics = Statistics::find()
                                            ->andWhere(['steam_id' => $steamId])
                                            ->andWhere(['server_tag' => $this->serverTag])
                                            ->andWhere(['key' => 'playtime'])
                                            ->andWhere(['wipe' => $this->wipeDate])
                                            ->indexBy('key')
                                            ->all();
                    Yii::$app->queueTop->push(new UpdateTopJob([
                                                                   'userId' => $user->id,
                                                                   'key' => 'playtime',
                                                                   'value' => $playTime,
                                                                   'serverId' => $this->serverId,
                                                                   'wipeDate' => $this->wipeDate,
                                                               ]));
                    if (!empty($statistics['playtime'])) {
                        $statistics['playtime']->value += $playTime;
                        $statistics['playtime']->save();
                    } else {
                        $model = new Statistics();
                        $model->steam_id = $steamId;
                        $model->server_tag = $this->serverTag;
                        $model->key = 'playtime';
                        $model->value = $playTime;
                        $model->wipe = $this->wipeDate;
                        $model->save();
                    }
                }
                Yii::$app->cache->delete($cacheKey);
            } else {
                Yii::$app->cache->set($cacheKey, $data, 10*60);
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("UpdateOnlineJob: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }
}