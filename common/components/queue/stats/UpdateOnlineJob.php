<?php

namespace common\components\queue\stats;

use common\components\oauth\Steam;
use common\components\queue\process\UserSteamInfoUpdateJob;
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
                $items = $data['items'];
                if (!empty($items)) {
                    $now = date('Y-m-d H:i:s');
                    $statRows = [];
                    $steamIds = [];
                    foreach ($items as $steamId => $playTime) {
                        $steamId = (string) $steamId;
                        if (strlen($steamId) !== 17) {
                            continue;
                        }
                        $steamIds[] = $steamId;
                        $delta = (int) $playTime;
                        if ($delta !== 0) {
                            $statRows[] = [$steamId, $this->serverTag, 'playtime', $delta, $this->wipeDate];
                        }
                    }
                    $steamIds = array_values(array_unique($steamIds));
                    Statistics::batchUpsertIncrementValues($statRows);
                    if ($steamIds !== []) {
                        $existing = User::find()
                            ->select(['steam_id'])
                            ->where(['steam_id' => $steamIds])
                            ->column();
                        $existingSet = array_fill_keys($existing, true);
                        foreach ($steamIds as $sid) {
                            if (!isset($existingSet[$sid])) {
                                User::findBySteamId($sid, false, 'online');
                            }
                        }
                        User::updateAll(
                            ['server_id' => $this->serverId, 'last_visit_server_at' => $now],
                            ['steam_id' => $steamIds]
                        );
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