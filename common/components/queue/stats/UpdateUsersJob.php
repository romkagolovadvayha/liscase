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
use yii\helpers\HtmlPurifier;
use yii\queue\JobInterface;

class UpdateUsersJob extends BaseObject implements JobInterface
{
    public $data;
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
            $request = json_decode($this->data, 1);
            /** @var Servers $server */
            $server = Servers::find()
                             ->cache(60)
                             ->andWhere(['tag' => $this->serverTag])
                             ->one();
            if ($server === null) {
                return;
            }
            $playTimeCount = 5;
            $wipe = $server->currentWipe();
            $playtimeRows = [];
            foreach ($request['users'] as $item) {
                try {
                    $user = User::findBySteamId($item['steam_id'], false, 'update user');
                    if (empty($user)) {
                        Statistics::batchUpsertIncrementValues($playtimeRows);
                        Yii::$app->telegramChats->sendMessage("UpdateUsersJob: user empty " . $item['steam_id']);
                        return;
                    }
                    if (strtotime($user->updated_at) < time() - 24 * 60 * 60) {
                        \Yii::$app->queueProcess->push(new UserSteamInfoUpdateJob(['steamId' => $user->steam_id]));
                    }
                    $user->username = HtmlPurifier::process($item['username']);
                    $user->ip = $item['ip'];
                    $user->ping = $item['ping'];
                    $user->server_id = $server->id;
                    $user->last_visit_server_at = date('Y-m-d H:i:s');
                    $user->save();
                    $playtimeRows[] = [$item['steam_id'], $this->serverTag, 'playtime', $playTimeCount, $wipe];
                } catch (\Exception $e) {
                    Yii::$app->telegramChats->sendMessage("UpdateOnlinesJob: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                }
            }
            Statistics::batchUpsertIncrementValues($playtimeRows);
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("UpdateOnlinesJob: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }
}