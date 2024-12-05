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
                             ->andWhere(['status' => Servers::STATUS_ACTIVE])
                             ->orderBy(['sort' => SORT_ASC])
                             ->one();
            if (empty($server)) {
                return;
            }
            $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
            foreach ($request['users'] as $steamId => $params) {
                try {
                    $user = User::findBySteamId($steamId);
                } catch (\Exception $ex) {}
                $statistics = Statistics::find()
                                        ->andWhere(['steam_id' => $steamId])
                                        ->andWhere(['server_tag' => $this->serverTag])
                                        ->andWhere(['wipe' => $wipeDate])
                                        ->indexBy('key')
                                        ->all();

                try {
                    if (!empty($user) && isset($params['hasGroupGaimer']) && !$params['hasGroupGaimer'] && in_array($this->serverTag, ['nolimit', 'max3'])) {
                        if (!empty($statistics['playtime']) && $statistics['playtime']->value > 120) {
                            $command = "o.usergroup add \"{$user->steam_id}\" gamer";
                            RconTasks::execute($command);
                        }
                    }
                    if (!empty($user) && $user->server_id !== $server->id) {
                        $user->server_id = $server->id;
                        $user->save();
                    }
                } catch (\Exception $e) {
                    Yii::$app->telegramReports->sendMessage("SaveStatsJob HasGroupGaimer:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                }

                $params['playtime'] = 1;
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
                    User::findBySteamId($item['steam_id']);
                    if (strlen($item['dead']) >= 16) {
                        User::findBySteamId($item['dead']);
                    }
                } catch (\Exception $ex) {}
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

                    $user = User::findBySteamId($item['steam_id']);
                    $reportUser = User::findBySteamId($item['recepient_steam_id']);

                    $userChecking = \common\models\user\UserChecking::find()
                                                                     ->select([
                                                                                  'checking_at' => 'created_at',
                                                                              ])
                                                                     ->andWhere(['user_id' => $reportUser->id])
                                                                     ->andWhere(['>=', 'created_at', $server->wipe])
                                                                     ->orderBy(['created_at' => SORT_DESC])
                                                                     ->asArray()
                                                                     ->one();

                    $countQuery = Reports::find()
                        ->andWhere(['recepient_steam_id' => $reportUser->steam_id])
                        ->andWhere(['wipe' => $wipeDate])
                        ->andWhere(['server_tag' => $this->serverTag]);

                    if (!empty($userChecking)) {
                        $countQuery->andWhere(['>=', 'created_at', $userChecking['checking_at']]);
                    }

                    $count = $countQuery->count();

                    $kills = 0;
                    $deaths = 0;
                    $kd = 0;
                    $playtime = 0;

                    try {
                        $stats = Statistics::find()
                                            ->andWhere(['steam_id' => $reportUser->steam_id])
                                            ->andWhere(['server_tag' => $this->serverTag])
                                            ->andWhere(['wipe' => $wipeDate])
                                            ->indexBy('key')
                                            ->all();
                        $kills = Statistics::getParam($stats, 'kills');
                        $deaths = Statistics::getParam($stats, 'deaths');
                        if ($deaths > 1) {
                            $kd = $kills > 0 ? round($kills / $deaths, 1) : 0;
                        } else {
                            $kd = $kills;
                        }
                        $playtime = Statistics::getParam($stats, 'playtime');
                    } catch (\Exception $e) {
                        Yii::$app->telegramReports->sendMessage("SaveStatsJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                    }

                    $playHour = round($playtime/60, 1);

                    $message = "⚔ <b>Новая жалоба на игрока</b>" . PHP_EOL
                        . "Отправил: {$user->username} ({$user->steam_id})" . PHP_EOL . PHP_EOL
                        . "Подозреваемый: <a href=\"https://steamcommunity.com/profiles/{$reportUser->steam_id}\">{$reportUser->username}</a>" . PHP_EOL
                        . "SteamId: {$reportUser->steam_id}" . PHP_EOL
                        . "Причина: {$item['reason']}" . PHP_EOL
                        . "Кол-во репортов на игрока: <b>{$count}</b>" . PHP_EOL
                        . "Играл за вайп: {$playHour} ч." . PHP_EOL
                        . "Убийств: {$kills}" . PHP_EOL
                        . "Смертей: {$deaths}" . PHP_EOL
                        . "К/Д: {$kd}" . PHP_EOL
                        . "Сервер: {$server->name}";

                    $bans = "";
                    $bansExist = false;
                    $lastCheck = "";
                    $lastCheckExist = false;
                    try {
                        $rustCheck = Yii::$app->rustCheck->getInfo($reportUser->steam_id);
                        if (!empty($rustCheck['bans'])) {
                            foreach ($rustCheck['bans'] as $ban) {
                                $bansExist = true;
                                $bans .= $ban['serverName'] . ":" . $ban['reason'] . PHP_EOL;
                            }
                        }
                        if (!empty($rustCheck['last_check'])) {
                            foreach ($rustCheck['last_check'] as $_lastCheck) {
                                $lastCheckExist = true;
                                $lastCheck .= $_lastCheck['serverName'] . PHP_EOL;
                            }
                        }
                    } catch (\Exception $e) {
                        Yii::$app->telegramReports->sendMessage("SaveStatsJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                    }
                    try {
                        $banList = Steam::getBansGGRust($reportUser->steam_id);
                        foreach ($banList as $banItem) {
                            $bansExist = true;
                            $bans .= $banItem['server'] . ":" . $banItem['reason'] . "; Срок: " . $banItem['expireDate'] . PHP_EOL;
                        }
                    } catch (\Exception $e) {
                        Yii::$app->telegramReports->sendMessage("SaveStatsJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                    }
                    try {
                        $banList = Steam::getBansRustRoom($reportUser->steam_id);
                        foreach ($banList as $banItem) {
                            $bansExist = true;
                            $bans .= $banItem['server'] . ":" . $banItem['reason'] . "; Срок: " . $banItem['expireDate'] . PHP_EOL;
                        }
                    } catch (\Exception $e) {
                        Yii::$app->telegramReports->sendMessage("SaveStatsJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                    }
                    try {
                        $banList = Steam::getBansRustUssr($reportUser->steam_id);
                        foreach ($banList as $banItem) {
                            $bansExist = true;
                            $bans .= $banItem['server'] . ":" . $banItem['reason'] . "; Срок: " . $banItem['expireDate'] . PHP_EOL;
                        }
                    } catch (\Exception $e) {
                        Yii::$app->telegramReports->sendMessage("SaveStatsJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                    }
                    try {
                        $banList = Steam::getBansMagicRust($reportUser->steam_id);
                        foreach ($banList as $banItem) {
                            $bansExist = true;
                            $bans .= $banItem['server'] . ":" . $banItem['reason'] . "; Срок: " . $banItem['expireDate'] . PHP_EOL;
                        }
                    } catch (\Exception $e) {
                        Yii::$app->telegramReports->sendMessage("SaveStatsJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                    }
                    try {
                        $banList = Steam::getBansRust($user->steam_id);
                        foreach ($banList as $banItem) {
                            $bansExist = true;
                            $bans .= $banItem['server'] . ":" . $banItem['reason'] . "; Дата: " . $banItem['date'] . "; Срок: " . $banItem['expireDate'] . PHP_EOL;
                        }
                    } catch (\Exception $e) {
                        Yii::$app->telegramReports->sendMessage("SaveStatsJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                    }
                    $hoursExist = false;
                    $hours = 0;
                    try {
                        $games = Steam::getGameInfo($reportUser->steam_id);
                        foreach ($games as $game) {
                            if ($game['appid'] == 252490) {
                                $hoursExist = true;
                                $hours = $game['playtime_forever'];
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        Yii::$app->telegramReports->sendMessage("SaveStatsJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
                    }

                    if ($hoursExist) {
                        $hours = round($hours/60, 1);
                        $message .=  PHP_EOL . "Часов в Steam: " . $hours;
                    }
                    if ($bansExist) {
                        $message .=  PHP_EOL  . PHP_EOL . "Найдены баны на других проектах:" . PHP_EOL . $bans;
                    }
                    if ($lastCheckExist) {
                        $message .=  PHP_EOL  . PHP_EOL . "Последние проверки игрока:" . PHP_EOL . $lastCheck;
                    }
                    Yii::$app->telegramReports->sendMessage($message);
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
            $server->save();
        } catch (\Exception $e) {
            Yii::error("SaveStatsJob: " . $e->getMessage(), 'error');
            Yii::$app->cache->delete('usersTop');
            Yii::$app->cache->delete('usersLive');
        }
    }
}