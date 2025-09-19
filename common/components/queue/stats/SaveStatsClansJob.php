<?php

namespace common\components\queue\stats;

use common\components\oauth\Steam;
use common\models\clan\Clan;
use common\models\clan\ClanStats;
use common\models\clan\UserClan;
use common\models\clan\UserClanStats;
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

class SaveStatsClansJob extends BaseObject implements JobInterface
{
    public $params;
    public $wipeDate;
    /** @var Servers */
    public $server;
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
            /** @var UserClan[] $userClans */
            $userClans = UserClan::find()
                ->andWhere(['status' => 1])
                ->indexBy('steam_id')
                ->all();

            if (!isset($userClans[$this->steamId])) {
                return;
            }

            $userClan = $userClans[$this->steamId];

            /** @var UserClanStats[] $statistics */
            $statistics = UserClanStats::find()
                                 ->andWhere(['user_clan_id' => $userClan->id])
                                 ->andWhere(['wipe' => $this->wipeDate])
                                 ->andWhere(['server_id' => $this->server->id])
                                 ->indexBy('key')
                                 ->all();

            $dbTransaction = Yii::$app->db->beginTransaction();
            foreach ($this->params as $key => $value) {
                if (!empty($statistics[$key])) {
                    $statistics[$key]->updated_at   = date('Y-m-d H:i:s');
                    $statistics[$key]->value += $value;
                    $statistics[$key]->save();
                } else {
                    $model               = new UserClanStats();
                    $model->user_clan_id = $userClan->id;
                    $model->steam_id     = $this->steamId;
                    $model->clan_id      = $userClan->clan_id;
                    $model->server_id    = $this->server->id;
                    $model->key          = $key;
                    $model->value        = $value;
                    $model->wipe         = $this->wipeDate;
                    $model->updated_at   = date('Y-m-d H:i:s');
                    $model->created_at   = date('Y-m-d H:i:s');
                    $model->save(false);
                }
            }
            Clan::recalculate($this->server, $userClan->clan_id);
            $dbTransaction->commit();
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("SaveStatsClansJob: " . PHP_EOL . $e->getFile() . ": " . $e->getLine() . PHP_EOL . $e->getMessage());
        }
    }
}