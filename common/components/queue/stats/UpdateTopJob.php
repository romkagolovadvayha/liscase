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
            if ($this->key == 'playtime') {
                $data = [];
                $cacheKey = "UpdateTopJob_{$this->userId}_{$this->key}_{$this->serverId}_{$this->wipeDate}";
                if (Yii::$app->cache->get($cacheKey)) {
                    $data = Yii::$app->cache->get($cacheKey);
                }
                if (empty($data['items'])) {
                    $data['value'] = 1;
                    $data['time'] = time();
                } else {
                    $data['value']++;
                }

                if (time() - $data['time'] >= 5 * 60) {
                    UserTop::updateTop($this->userId, $this->key, $this->value, $this->serverId, $this->wipeDate);
                    Yii::$app->cache->delete($cacheKey);
                } else {
                    Yii::$app->cache->set($cacheKey, $data, 10*60);
                }
            } else {
                UserTop::updateTop($this->userId, $this->key, $this->value, $this->serverId, $this->wipeDate);
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("UpdateTopJob::updateTop(user, key, value, server, wipeDate): " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }
}