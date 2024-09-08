<?php

namespace common\components\queue\stats;

use common\components\oauth\Steam;
use common\models\servers\Servers;
use common\models\statistics\Chats;
use common\models\statistics\Kills;
use common\models\statistics\Reports;
use common\models\statistics\Statistics;
use common\models\statistics\Teams;
use common\models\user\User;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class SaveStatsJob extends BaseObject implements JobInterface
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
                             ->andWhere(['tag' => $this->serverTag])
                             ->one();
            if (empty($server)) {
                return;
            }
            $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
            foreach ($request['users'] as $steamId => $params) {
                $statistics = Statistics::find()
                                        ->andWhere(['steam_id' => $steamId])
                                        ->andWhere(['server_tag' => $this->serverTag])
                                        ->andWhere(['wipe' => $wipeDate])
                                        ->indexBy('key')
                                        ->all();
                $params['playtime'] = 3;
                foreach ($params as $key => $value) {
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
            }
            foreach ($request['kills'] as $item) {
                $model = new Kills();
                $model->steam_id = $item['steam_id'];
                $model->type = $item['type'];
                $model->dead = $item['dead'];
                $model->weapon = $item['weapon'];
                $model->distance = $item['distance'];
                $model->created_at = $item['date'];
                $model->server_tag = $this->serverTag;
                $model->wipe = $wipeDate;
                $model->save();
            }
            foreach ($request['teams'] as $item) {
                $model = new Teams();
                $model->steam_id = $item['steam_id'];
                $model->type = $item['type'];
                $model->team_author = $item['team_author'];
                $model->created_at = $item['created_at'];
                $model->server_tag = $this->serverTag;
                $model->wipe = $wipeDate;
                $model->save();
            }
            /*try {
                foreach ($request['chats'] as $item) {
                    $model = new Chats();
                    $model->steam_id = $item['steam_id'];
                    $model->message = $item['message'];
                    $model->created_at = $item['created_at'];
                    $model->server_tag = $this->serverTag;
                    $model->save();

                    $wordBlackList = ['мама', 'мамку', 'мать', 'маму', 'отец', 'отчим', 'сестр', 'админ', 'зеркал', 'ебал', 'серв'];

                    $user = User::findBySteamId($item['steam_id']);
                    foreach ($wordBlackList as $word) {
                        if (strpos(mb_strtolower($item['message']), $word) !== false) {
                            $message = "💭 <b>Подозрение на оскорбление</b>" . PHP_EOL
                                . "Отправил: {$user->username} ({$user->steam_id})" . PHP_EOL
                                . "Сообщение: {$item['message']}" . PHP_EOL
                                . "Сервер: {$server->name}";

                            Yii::$app->telegramChats->sendMessage($message, [
                                [
                                    'text' => 'Оскорбление родных',
                                    'url' => "https://prostoj.store/site/mute?steamId={$user->steam_id}&serverTag={$server->tag}&reason=Оскорбления%20родных",
                                ],
                                [
                                    'text' => 'Без причины',
                                    'url' => "https://prostoj.store/site/mute?steamId={$user->steam_id}&serverTag={$server->tag}&reason=Причина%20не%20указана",
                                ],
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                Yii::$app->telegramChats->sendMessage("SaveStatsJob:" . $e->getLine() . ":" . $e->getMessage());
            }*/
            try {
                foreach ($request['reports'] as $item) {
                    $model = new Reports();
                    $model->steam_id = $item['steam_id'];
                    $model->recepient_steam_id = $item['recepient_steam_id'];
                    $model->reason = $item['reason'];
                    $model->created_at = $item['created_at'];
                    $model->server_tag = $this->serverTag;
                    $model->wipe = $wipeDate;
                    $model->save();


                }
            } catch (\Exception $e) {
                Yii::$app->telegramReports->sendMessage("SaveStatsJob:" . $e->getLine() . ":" . $e->getMessage());
            }
            $server->players = $request['server']['online'] + 5;
            $server->joined = $request['server']['join'];
            $server->queued = $request['server']['queue'];
            $server->save();
        } catch (\Exception $e) {
            Yii::error("SaveStatsJob: " . $e->getMessage(), 'error');
            Yii::$app->cache->delete('usersTop');
            Yii::$app->cache->delete('usersLive');
        }
    }
}