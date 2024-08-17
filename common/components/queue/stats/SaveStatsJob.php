<?php

namespace common\components\queue\stats;

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
                $params['playtime'] = 7;
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
                    if (strpos($item['message'], $word) !== false) {
                        $message = "💭 <b>Подозрение на оскорбление</b>" . PHP_EOL
                            . "Отправил: {$user->username} ({$user->steam_id})" . PHP_EOL
                            . "Сообщение: {$item['message']}" . PHP_EOL
                            . "Сервер: {$server->name}" . PHP_EOL
                            . "`bcm.mute {$user->steam_id} 1h \"Оскорбления родных\"` ";

                        Yii::$app->telegramChats->sendMessage($message);
                    }
                }
            }
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

                $count = Reports::find()
                    ->andWhere(['recepient_steam_id' => $reportUser->steam_id])
                    ->andWhere(['wipe' => $wipeDate])
                    ->count();

                $message = "⚔ <b>Новая жалоба на игрока</b>" . PHP_EOL
                    . "Отправил: {$user->username} ({$user->steam_id})" . PHP_EOL . PHP_EOL
                    . "Подозреваемый: {$reportUser->username} ({$reportUser->steam_id})" . PHP_EOL
                    . "SteamId: {$reportUser->steam_id}" . PHP_EOL
                    . "Причина: {$item['reason']}" . PHP_EOL
                    . "Кол-во репортов на игрока: {$count}" . PHP_EOL
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
                            $bans .= $ban['serverName'] . ":" . $ban['reason'] . "; Дата: " . (new \DateTime($ban['banDate']))->format('Y-m-d H:i:s') . PHP_EOL;
                        }
                    }
                    if (!empty($rustCheck['last_check'])) {
                        foreach ($rustCheck['last_check'] as $_lastCheck) {
                            $lastCheckExist = true;
                            $lastCheck .= $_lastCheck['serverName'] . "; Дата: " . (new \DateTime($_lastCheck['time']))->format('Y-m-d H:i:s') . PHP_EOL;
                        }
                    }
                } catch (\Exception $e) {}

                if ($bansExist) {
                    $message .=  PHP_EOL  . PHP_EOL . "Найдены баны на других проектах:" . PHP_EOL . $bans;
                }
                if ($lastCheckExist) {
                    $message .=  PHP_EOL  . PHP_EOL . "Последние проверки игрока:" . PHP_EOL . $lastCheck;
                }

                Yii::$app->telegramReports->sendMessage($message);
            }
            $server->players = $request['server']['online'];
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