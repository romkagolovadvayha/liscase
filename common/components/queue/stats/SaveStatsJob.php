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
                             ->cache(60)
                             ->andWhere(['tag' => $this->serverTag])
                             ->one();
            if (empty($server)) {
                return;
            }
            $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
            try {
                Yii::$app->queueOnline->push(new UpdateOnlineJob([
                    'steam_ids' => array_keys($request['users']),
                    'serverId' => $server->id,
                    'serverTag' => $this->serverTag,
                    'wipeDate' => $wipeDate,
                ]));
            } catch (\Exception $e) {
                Yii::$app->telegramChats->sendMessage("SaveStatsJob::UpdateOnlineJob: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
            }
            foreach ($request['users'] as $steamId => $params) {
                if (empty($params)) {
                    continue;
                }
                try {
                    $user = User::findBySteamId($steamId);
                } catch (\Exception $e) {
                    Yii::$app->telegramReports->sendMessage("SaveStatsJob findBySteamId:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                }
                $statistics = Statistics::find()
                                        ->andWhere(['steam_id' => $steamId])
                                        ->andWhere(['server_tag' => $this->serverTag])
                                        ->andWhere(['wipe' => $wipeDate])
                                        ->indexBy('key')
                                        ->all();

                try {
                    /*if (!empty($user) && isset($params['hasGroupGaimer']) && !$params['hasGroupGaimer'] && in_array($this->serverTag, ['nolimit', 'max3'])) {
                        if (!empty($statistics['playtime']) && $statistics['playtime']->value > 120) {
                            $command = "o.usergroup add \"{$user->steam_id}\" gamer";
                            RconTasks::execute($command);
                        }
                    }*/
                    if (!empty($user)) {
                        if (empty($user->last_visit_server_at) || time() - strtotime($user->last_visit_server_at) > 300) {
                            $user->server_id = $server->id;
                            $user->last_visit_server_at = date('Y-m-d H:i:s');
                            $user->save();
                        }
                    }
                } catch (\Exception $e) {
                    Yii::$app->telegramReports->sendMessage("SaveStatsJob HasGroupGaimer:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                }

                $params['kills'] = 0;
                if (!empty($request['kills'])) {
                    try {
                        foreach ($request['kills'] as $item) {
                            if (strlen($item['steam_id']) < 16 || strlen($item['dead']) < 16 || $item['type'] != 'kill') {
                                continue;
                            }
                            if ($item['steam_id'] == $steamId) {
                                $params['kills']++;
                            }
                        }
                    } catch (\Exception $e) {
                        Yii::$app->telegramReports->sendMessage("SaveStatsJob:" . $e->getLine() . ":" . $e->getMessage());
                    }
                }
                foreach ($params as $key => $value) {
                    if (empty($value)) {
                        continue;
                    }
                    try {
                        Yii::$app->queueTop->push(new UpdateTopJob([
                            'userId' => $user->id,
                            'key' => $key,
                            'value' => $value,
                            'serverId' => $server->id,
                            'wipeDate' => $wipeDate,
                        ]));
                    } catch (\Exception $e) {
                        Yii::$app->telegramChats->sendMessage("SaveStatsJob::updateTop(user, key, value, server, wipeDate): " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                    }
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
                try {
                    Yii::$app->queueKills->push(new UpdateKillsJob([
                        'item' => $item,
                        'serverTag' => $this->serverTag,
                        'wipeDate' => $wipeDate,
                    ]));
                } catch (\Exception $e) {
                    Yii::$app->telegramChats->sendMessage("SaveStatsJob::queueKills: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                }
            }
            foreach ($request['teams'] as $item) {
                try {
                    User::findBySteamId($item['steam_id']);
                } catch (\Exception $ex) {}
                $model = new Teams();
                $model->steam_id = $item['steam_id'];
                $model->type = $item['type'];
                $model->team_author = $item['team_author'];
                $model->created_at = $item['created_at'];
                $model->server_tag = $this->serverTag;
                $model->wipe = $wipeDate;
                $model->save();

                try {
                    Yii::$app->queueTeam->push(new UpdateTeamJob([
                        'model' => $model,
                        'server' => $server,
                        'wipeDate' => $wipeDate,
                    ]));
                } catch (\Exception $e) {
                    Yii::$app->telegramChats->sendMessage("SaveStatsJob::updateTeam: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                }
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
                    try {
                        Yii::$app->queueReport->push(new UpdateReportJob([
                            'item' => $item,
                            'serverTag' => $this->serverTag,
                            'serverName' => $server->name,
                            'wipeDate' => $wipeDate,
                            'wipe' => $server->wipe,
                        ]));
                    } catch (\Exception $e) {
                        Yii::$app->telegramChats->sendMessage("SaveStatsJob::updateTeam: " . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                Yii::$app->telegramReports->sendMessage("SaveStatsJob:" . $e->getLine() . ":" . $e->getMessage());
            }


            $server->players = $request['server']['online'];
            if ($request['server']['online'] > 50) {
                $server->players = $request['server']['online'] + 5;
            }
            $server->joined = $request['server']['join'];
            $server->queued = $request['server']['queue'];
            $server->updated_at = date('Y-m-d H:i:s');
            $server->status = Servers::STATUS_ACTIVE;
            $server->save();
        } catch (\Exception $e) {
            Yii::error("SaveStatsJob: " . $e->getMessage(), 'error');
            Yii::$app->cache->delete('usersTop');
            Yii::$app->cache->delete('usersLive');
        }
    }
}