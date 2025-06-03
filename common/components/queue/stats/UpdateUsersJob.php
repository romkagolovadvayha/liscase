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
            $playTimeCount = 5;
            foreach ($request['users'] as $item) {
                try {
                    $user = User::findBySteamId($item['steam_id']);
                    if (empty($user)) {
                        Yii::$app->telegramChats->sendMessage("UpdateUsersJob: user empty " . $item['steam_id']);
                        return;
                    }
                    $user->username = HtmlPurifier::process($item['username']);
                    $user->ip = $item['ip'];
                    $user->ping = $item['ping'];
                    $user->server_id = $server->id;
                    $user->last_visit_server_at = date('Y-m-d H:i:s');
                    $user->save();
                    /** @var Statistics $playtime */
                    $playtime = Statistics::find()
                                           ->andWhere(['steam_id' => $item['steam_id']])
                                           ->andWhere(['server_tag' => $this->serverTag])
                                           ->andWhere(['key' => 'playtime'])
                                           ->andWhere(['wipe' => $server->currentWipe()])
                                           ->orderBy(['id' => SORT_ASC])
                                           ->one();
                    if (!empty($playtime)) {
                        $playtime->value += $playTimeCount;
                        $playtime->save();
                    } else {
                        $model = new Statistics();
                        $model->steam_id = $item['steam_id'];
                        $model->server_tag = $this->serverTag;
                        $model->key = 'playtime';
                        $model->value = $playTimeCount;
                        $model->wipe = $server->currentWipe();
                        $model->save();
                    }
                } catch (\Exception $e) {
                    Yii::$app->telegramChats->sendMessage("UpdateOnlinesJob: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("UpdateOnlinesJob: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }
}