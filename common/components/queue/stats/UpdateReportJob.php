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
use GeoIp2\Database\Reader;
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

    private function country($code) {
        $list = [
            'ru' => ['icon' => '🇷🇺', 'name' => 'Россия'],
            'by' => ['icon' => '🇧🇾', 'name' => 'Беларусь'],
            'kz' => ['icon' => '🇰🇿', 'name' => 'Казахстан'],
            'ua' => ['icon' => '🇺🇦', 'name' => 'Украина'],
            'us' => ['icon' => '🇺🇸', 'name' => 'США'],
            'de' => ['icon' => '🇩🇪', 'name' => 'Германия'],
            'fr' => ['icon' => '🇫🇷', 'name' => 'Франция'],
            'gb' => ['icon' => '🇬🇧', 'name' => 'Великобритания'],
            'it' => ['icon' => '🇮🇹', 'name' => 'Италия'],
            'es' => ['icon' => '🇪🇸', 'name' => 'Испания'],
            'cn' => ['icon' => '🇨🇳', 'name' => 'Китай'],
            'jp' => ['icon' => '🇯🇵', 'name' => 'Япония'],
            'in' => ['icon' => '🇮🇳', 'name' => 'Индия'],
            'br' => ['icon' => '🇧🇷', 'name' => 'Бразилия'],
            'ca' => ['icon' => '🇨🇦', 'name' => 'Канада'],
            'au' => ['icon' => '🇦🇺', 'name' => 'Австралия'],
            'nl' => ['icon' => '🇳🇱', 'name' => 'Нидерланды'],
            'se' => ['icon' => '🇸🇪', 'name' => 'Швеция'],
            'ch' => ['icon' => '🇨🇭', 'name' => 'Швейцария'],
            'pl' => ['icon' => '🇵🇱', 'name' => 'Польша'],
            'kr' => ['icon' => '🇰🇷', 'name' => 'Южная Корея'],
            'sa' => ['icon' => '🇸🇦', 'name' => 'Саудовская Аравия'],
            'ae' => ['icon' => '🇦🇪', 'name' => 'ОАЭ'],
            'sg' => ['icon' => '🇸🇬', 'name' => 'Сингапур'],
            'mx' => ['icon' => '🇲🇽', 'name' => 'Мексика'],
            'ar' => ['icon' => '🇦🇷', 'name' => 'Аргентина'],
            'ng' => ['icon' => '🇳🇬', 'name' => 'Нигерия'],
            'za' => ['icon' => '🇿🇦', 'name' => 'Южноафриканская Республика'],
            'ke' => ['icon' => '🇰🇪', 'name' => 'Кения'],
            'gh' => ['icon' => '🇬🇭', 'name' => 'Гана'],
            'eg' => ['icon' => '🇪🇬', 'name' => 'Египет'],
            'pk' => ['icon' => '🇵🇰', 'name' => 'Пакистан'],
            'bd' => ['icon' => '🇧🇩', 'name' => 'Бангладеш'],
            'vn' => ['icon' => '🇻🇳', 'name' => 'Вьетнам'],
            'th' => ['icon' => '🇹🇭', 'name' => 'Таиланд'],
            'ph' => ['icon' => '🇵🇭', 'name' => 'Филиппины'],
            'ro' => ['icon' => '🇷🇴', 'name' => 'Румыния'],
            'cz' => ['icon' => '🇨🇿', 'name' => 'Чехия'],
            'hu' => ['icon' => '🇭🇺', 'name' => 'Венгрия'],
            'gr' => ['icon' => '🇬🇷', 'name' => 'Греция'],
            'no' => ['icon' => '🇳🇴', 'name' => 'Норвегия'],
            'fi' => ['icon' => '🇫🇮', 'name' => 'Финляндия'],
            'dk' => ['icon' => '🇩🇰', 'name' => 'Дания'],
            'at' => ['icon' => '🇦🇹', 'name' => 'Австрия'],
            'be' => ['icon' => '🇧🇪', 'name' => 'Бельгия'],
            'ie' => ['icon' => '🇮🇪', 'name' => 'Ирландия'],
            'lu' => ['icon' => '🇱🇺', 'name' => 'Люксембург'],
            'lt' => ['icon' => '🇱🇹', 'name' => 'Литва'],
            'lv' => ['icon' => '🇱🇻', 'name' => 'Латвия'],
            'ee' => ['icon' => '🇪🇪', 'name' => 'Эстония'],
            'hr' => ['icon' => '🇭🇷', 'name' => 'Хорватия'],
            'si' => ['icon' => '🇸🇮', 'name' => 'Словения'],
            'sk' => ['icon' => '🇸🇰', 'name' => 'Словакия'],
            'bg' => ['icon' => '🇧🇬', 'name' => 'Болгария'],
            'ba' => ['icon' => '🇧🇦', 'name' => 'Босния и Герцеговина'],
            'me' => ['icon' => '🇲🇪', 'name' => 'Черногория'],
            'mk' => ['icon' => '🇲🇰', 'name' => 'Северная Македония'],
            'rs' => ['icon' => '🇷🇸', 'name' => 'Сербия'],
            'al' => ['icon' => '🇦🇱', 'name' => 'Албания'],
            'am' => ['icon' => '🇦🇲', 'name' => 'Армения'],
            'ge' => ['icon' => '🇬🇪', 'name' => 'Грузия'],
            'cy' => ['icon' => '🇨🇾', 'name' => 'Кипр'],
            'mt' => ['icon' => '🇲🇹', 'name' => 'Мальта'],
            'is' => ['icon' => '🇮🇸', 'name' => 'Исландия'],
        ];
        if (empty($list[$code])) {
            return null;
        }
        return $list[$code];
    }

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
            $reason = !empty($item['reason']) ? $item['reason'] : 'Не указана';
            $message = "⚔ <b>{$this->serverName}</b>" . PHP_EOL
                . "Отправил: {$user->username} (<code>{$user->steam_id}</code>)" . PHP_EOL . PHP_EOL
                . "Подозреваемый: <a href=\"https://steamcommunity.com/profiles/{$reportUser->steam_id}\">{$reportUser->username}</a>" . PHP_EOL
                . "SteamId: <code>{$reportUser->steam_id}</code>" . PHP_EOL
                . "Причина: <b>{$reason}</b>" . PHP_EOL
                . "Кол-во репортов на игрока: <b>{$count}</b>" . PHP_EOL
                . "Играл за вайп: {$playHour} ч." . PHP_EOL;


            if (!empty($reportUser->ping)) {
                $message .= "IP: <code>{$reportUser->ip}</code>" . PHP_EOL;
                $message .= "Пинг: {$reportUser->ping}ms" . PHP_EOL;
            }
            $countryItem = $this->country($reportUser->getCountryByIp());
            if (!empty($countryItem)) {
                $message .= "Страна: {$countryItem['icon']} {$countryItem['name']}" . PHP_EOL;
            }

            $message .=  PHP_EOL . "Килы: {$kills}/{$deaths} (К/Д: {$kd})";

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
//            try {
//                $banList = Steam::getBansRustUssr($reportUser->steam_id);
//                foreach ($banList as $banItem) {
//                    $bansExist = true;
//                    $bans .= $banItem['server'] . ":" . $banItem['reason'] . "; Срок: " . $banItem['expireDate'] . PHP_EOL;
//                }
//            } catch (\Exception $e) {
//                Yii::$app->telegramChats->sendMessage("UpdateReportJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
//            }
            try {
                $banList = Steam::getBansMagicRust($reportUser->steam_id);
                foreach ($banList as $banItem) {
                    $bansExist = true;
                    $bans .= $banItem['server'] . ":" . $banItem['reason'] . "; Срок: " . $banItem['expireDate'] . PHP_EOL;
                }
            } catch (\Exception $e) {
                Yii::$app->telegramChats->sendMessage("UpdateReportJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
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