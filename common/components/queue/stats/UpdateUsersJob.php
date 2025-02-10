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
            $steamIds = [];
            foreach ($request['users'] as $item) {
                $steamIds[] = $item['steam_id'];
            }
            /** @var Statistics[] $playtimes */
            $playtimes = Statistics::find()
                                    ->andWhere(['IN', 'steam_id', $steamIds])
                                    ->andWhere(['server_tag' => $this->serverTag])
                                    ->andWhere(['key' => 'playtime'])
                                    ->andWhere(['wipe' => $server->currentWipe()])
                                    ->indexBy('steam_id')
                                    ->one();
            $playTime = 5;
            foreach ($request['users'] as $item) {
                try {
                    $user = User::findBySteamId($item['steam_id']);
                    $user->username = HtmlPurifier::process($item['username']);
                    $user->server_id = $server->id;
                    $user->last_visit_server_at = date('Y-m-d H:i:s');
                    $user->save();
                    if (!empty($playtimes[$item['steam_id']])) {
                        $playtimes[$item['steam_id']]->value += $playTime;
                        $playtimes[$item['steam_id']]->save();
                    } else {
                        $model = new Statistics();
                        $model->steam_id = $item['steam_id'];
                        $model->server_tag = $this->serverTag;
                        $model->key = 'playtime';
                        $model->value = $playTime;
                        $model->wipe = $server->currentWipe();
                        $model->save();
                    }
                    Yii::$app->queueTop->push(new UpdateTopJob([
                        'userId' => $user->id,
                        'key' => 'playtime',
                        'value' => $playTime,
                        'serverId' => $server->id,
                        'wipeDate' => $server->currentWipe(),
                    ]));
                } catch (\Exception $e) {
                    Yii::$app->telegramChats->sendMessage("UpdateOnlinesJob: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("UpdateOnlinesJob: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }
}