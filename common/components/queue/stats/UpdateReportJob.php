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

class UpdateReportJob extends BaseObject implements JobInterface
{
    public $item;
    public $serverTag;
    public $serverName;
    public $wipeDate;
    public $wipe;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        $item = $this->item;
        try {
            $model = new Reports();
            $model->steam_id = $item['steam_id'];
            $model->recepient_steam_id = $item['recepient_steam_id'];
            $model->reason = $item['reason'];
            $model->created_at = $item['created_at'];
            $model->server_tag = $this->serverTag;
            $model->wipe = $this->wipeDate;
            $model->save();

            $user = User::findBySteamId($item['steam_id']);
            $reportUser = User::findBySteamId($item['recepient_steam_id']);

            $countQuery = Reports::find()
                                 ->andWhere(['recepient_steam_id' => $reportUser->steam_id])
                                 ->andWhere(['wipe' => $this->wipeDate])
                                 ->andWhere(['server_tag' => $this->serverTag]);

            $count = $countQuery->count();

            $kills = 0;
            $deaths = 0;
            $kd = 0;
            $playtime = 0;

            try {
                $stats = Statistics::find()
                                   ->andWhere(['steam_id' => $reportUser->steam_id])
                                   ->andWhere(['server_tag' => $this->serverTag])
                                   ->andWhere(['wipe' => $this->wipeDate])
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
                Yii::$app->telegramChats->sendMessage("UpdateReportJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
            }

            $playHour = round($playtime/60, 1);

            $message = "⚔ <b>Новая жалоба на игрока</b>" . PHP_EOL
                . "Отправил: {$user->username} (<code>{$user->steam_id}</code>)" . PHP_EOL . PHP_EOL
                . "Подозреваемый: <a href=\"https://steamcommunity.com/profiles/{$reportUser->steam_id}\">{$reportUser->username}</a>" . PHP_EOL
                . "SteamId: <code>{$reportUser->steam_id}</code>" . PHP_EOL
                . "Причина: {$item['reason']}" . PHP_EOL
                . "Кол-во репортов на игрока: <b>{$count}</b>" . PHP_EOL
                . "Играл за вайп: {$playHour} ч." . PHP_EOL
                . "Убийств: {$kills}" . PHP_EOL
                . "Смертей: {$deaths}" . PHP_EOL
                . "К/Д: {$kd}" . PHP_EOL
                . "Сервер: {$this->serverName}";

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
                Yii::$app->telegramChats->sendMessage("UpdateReportJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
            }
            try {
                $banList = Steam::getBansGGRust($reportUser->steam_id);
                foreach ($banList as $banItem) {
                    $bansExist = true;
                    $bans .= $banItem['server'] . ":" . $banItem['reason'] . "; Срок: " . $banItem['expireDate'] . PHP_EOL;
                }
            } catch (\Exception $e) {
                Yii::$app->telegramChats->sendMessage("UpdateReportJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
            }
            try {
                $banList = Steam::getBansRustRoom($reportUser->steam_id);
                foreach ($banList as $banItem) {
                    $bansExist = true;
                    $bans .= $banItem['server'] . ":" . $banItem['reason'] . "; Срок: " . $banItem['expireDate'] . PHP_EOL;
                }
            } catch (\Exception $e) {
                Yii::$app->telegramChats->sendMessage("UpdateReportJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
            }
            try {
                $banList = Steam::getBansRustUssr($reportUser->steam_id);
                foreach ($banList as $banItem) {
                    $bansExist = true;
                    $bans .= $banItem['server'] . ":" . $banItem['reason'] . "; Срок: " . $banItem['expireDate'] . PHP_EOL;
                }
            } catch (\Exception $e) {
                Yii::$app->telegramChats->sendMessage("UpdateReportJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
            }
            try {
                $banList = Steam::getBansMagicRust($reportUser->steam_id);
                foreach ($banList as $banItem) {
                    $bansExist = true;
                    $bans .= $banItem['server'] . ":" . $banItem['reason'] . "; Срок: " . $banItem['expireDate'] . PHP_EOL;
                }
            } catch (\Exception $e) {
                Yii::$app->telegramChats->sendMessage("UpdateReportJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
            }
            try {
                $banList = Steam::getBansRust($user->steam_id);
                foreach ($banList as $banItem) {
                    $bansExist = true;
                    $bans .= $banItem['server'] . ":" . $banItem['reason'] . "; Дата: " . $banItem['date'] . "; Срок: " . $banItem['expireDate'] . PHP_EOL;
                }
            } catch (\Exception $e) {
                Yii::$app->telegramChats->sendMessage("UpdateReportJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
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
                Yii::$app->telegramChats->sendMessage("UpdateReportJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
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
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("UpdateReportJob" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }
}