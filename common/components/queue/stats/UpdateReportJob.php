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
use yii\helpers\Html;
use common\components\bansystem\dto\RustAppPlayerResponse;

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

            $user = User::findBySteamId($item['steam_id'], false, 'report');
            $reportUser = User::findBySteamId($item['recepient_steam_id'], false, 'report 2');
            $server = Servers::findOne(['tag' => $this->serverTag, 'status' => Servers::STATUS_ACTIVE]);
            
            // Отправляем уведомление пользователю о включении оповещений о банах
            if ($user && (empty($user->telegram_chat_id) || $user->is_telegram_blocked || !$user->ban_notify)) {
                if ($server) {
                    $user->sendBanNotifyPromoMessage($server);
                }
            }

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
                $playtimeWipe = Statistics::getParam($stats, 'playtime');
            } catch (\Exception $e) {
                Yii::$app->telegramChats->sendMessage("UpdateReportJob:" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
            }

            $totalPlaytime = (int)Statistics::find()
                ->select('SUM(value)')
                ->andWhere(['steam_id' => $reportUser->steam_id, 'key' => 'playtime'])
                ->scalar();
            $playtimeWipe = $playtimeWipe ?? 0;
            $totalPlaytime = max(0, $totalPlaytime);

            $playHourTotal = round($totalPlaytime / 60, 1);
            $playHourWipe = round($playtimeWipe / 60, 1);

            /** @var RustAppPlayerResponse|null $rustAppData */
            $rustAppData = null;
            if (Yii::$app->has('rustApp')) {
                try {
                    $rustAppData = Yii::$app->rustApp->player($reportUser->steam_id);
                } catch (\Throwable $throwable) {
                    Yii::error('RustApp player fetch failed: ' . $throwable->getMessage(), __METHOD__);
                }
            }

            $reason = !empty($item['reason']) ? $item['reason'] : 'Не указана';
            $countryCode = strtolower((string)$reportUser->getCountryByIp());
            $countryItem = $this->country($countryCode);

            $message = $this->buildReportMessage(
                $user,
                $reportUser,
                $reason,
                $count,
                $playHourTotal,
                $totalPlaytime,
                $playtimeWipe,
                $playHourWipe,
                $kills,
                $deaths,
                $kd,
                $countryItem
            );

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

            $rustAppLines = $this->buildRustAppLines($rustAppData, $server, $reportUser, $this->serverName);
            if (!empty($rustAppLines)) {
                $message .= PHP_EOL . PHP_EOL . implode(PHP_EOL, $rustAppLines);
            }
            Yii::$app->telegramReports->sendMessage($message);

            if ($this->shouldNotifyRedFlag($totalPlaytime, $countryCode)) {
                $this->sendRedFlagNotification(
                    $reportUser,
                    $server,
                    $reason,
                    $count,
                    $kills,
                    $deaths,
                    $kd,
                    $totalPlaytime,
                    $playHourTotal,
                    $playtimeWipe,
                    $playHourWipe,
                    $countryItem,
                    $bansExist,
                    $bans,
                    $lastCheckExist,
                    $lastCheck,
                    $rustAppData
                );
            }
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("UpdateReportJob" . $e->getFile() . $e->getLine() . ":" . $e->getMessage());
        }
    }

    private function shouldNotifyRedFlag(int $playtime, string $countryCode): bool
    {
        if (!Yii::$app->has('telegramRedFlag')) {
            return false;
        }

         if ($playtime >= 60) {
             return false;
         }

         if ($countryCode === '' || $countryCode === 'ru') {
             return false;
         }

        return true;
    }

    private function sendRedFlagNotification(
        User $reportUser,
        ?Servers $server,
        string $reason,
        int $count,
        int $kills,
        int $deaths,
        float $kd,
        int $totalPlaytime,
        float $totalPlayHour,
        int $playtimeWipe,
        float $playHourWipe,
        ?array $countryItem,
        bool $bansExist,
        string $bans,
        bool $lastCheckExist,
        string $lastCheck,
        ?RustAppPlayerResponse $rustAppData
    ): void {
        try {
            $formatter = Yii::$app->formatter;
            $lines = [];
            $lines[] = '🚩 <b>RedFlag: подозрительный игрок</b>';
            $lines[] = '';

            $lines[] = '<b>Об игроке:</b>';
            if ($server) {
                $lines[] = 'Сервер: <b>' . Html::encode($server->name) . '</b> (' . Html::encode($this->serverTag) . ')';
            } else {
                $lines[] = 'Сервер: ' . Html::encode($this->serverName) . ' (' . Html::encode($this->serverTag) . ')';
            }
            $lines[] = 'Причина жалобы: ' . Html::encode($reason);
            $lines[] = 'Жалоб за вайп: <b>' . Html::encode((string)$count) . '</b>';
            $playerLink = 'https://steamcommunity.com/profiles/' . $reportUser->steam_id;
            $lines[] = '';
            $lines[] = 'Игрок: <a href="' . Html::encode($playerLink) . '">' . Html::encode($reportUser->username) . '</a> (<code>' . Html::encode($reportUser->steam_id) . '</code>)';
            if ($countryItem) {
                $lines[] = 'Страна: ' . Html::encode($countryItem['name']) . ' ' . $countryItem['icon'];
            }
            $lines[] = 'На сервере (всего): <b>' . Html::encode(number_format($totalPlayHour, 1)) . 'ч</b> (' . Html::encode((string)$totalPlaytime) . ' мин)';
            $lines[] = 'Килы / смерти: <b>' . Html::encode((string)$kills) . '</b> / <b>' . Html::encode((string)$deaths) . '</b>';
            $lines[] = 'K/D: <b>' . Html::encode(number_format($kd, 2)) . '</b>';
            if (!empty($reportUser->created_at)) {
                $lines[] = 'Аккаунт создан: ' . Html::encode($formatter->asDatetime($reportUser->created_at, 'php:d.m.Y H:i'));
            }

            if ($bansExist && !empty(trim($bans))) {
                $lines[] = '';
                $lines[] = '<b>Баны на других проектах:</b>';
                $lines[] = nl2br(Html::encode(trim($bans)));
            }

            if ($lastCheckExist && !empty(trim($lastCheck))) {
                $lines[] = '';
                $lines[] = '<b>Последние проверки:</b>';
                $lines[] = nl2br(Html::encode(trim($lastCheck)));
            }

            $rustAppLines = $this->buildRustAppLines($rustAppData, $server, $reportUser, $this->serverName);
            if (!empty($rustAppLines)) {
                $lines[] = '';
                $lines = array_merge($lines, $rustAppLines);
            }

            $buttons = $this->buildRedFlagButtons($reportUser, $server);

            $messageBody = implode("\n", $lines);
            Yii::$app->telegramRedFlag->sendMessage($messageBody, $buttons);
        } catch (\Exception $throwable) {
            Yii::error('Failed to send RedFlag notification: ' . $throwable->getMessage(), __METHOD__);
        }
    }

    /**
     * @param RustAppPlayerResponse|null $rustAppData
     * @return array
     */
    private function buildRustAppLines(?RustAppPlayerResponse $rustAppData, ?Servers $server, User $reportUser, string $serverName): array
    {
        if (!$rustAppData || !$rustAppData->player) {
            return [];
        }

        $formatter = Yii::$app->formatter;
        $player = $rustAppData->player;

        if ($player->ipDetails) {
            $details = $player->ipDetails;
            if ($details->countryName || $details->city) {
                $countryCity = array_filter([
                    $details->countryName ? Html::encode($details->countryName) : null,
                    $details->city ? Html::encode($details->city) : null,
                ]);
                if (!empty($countryCity)) {
                    $lines[] = 'Город: ' . implode(', ', $countryCity);
                }
            }
            if ($details->provider) {
                $lines[] = 'Провайдер: ' . Html::encode($details->provider);
            }
            if ($details->proxy !== null) {
                $lines[] = 'VPN: ' . ($details->proxy ? 'да' : 'нет');
            }
        }

        $lines[] = '';
        $lines[] = '<b>Информация из Steam:</b>';

        if ($player->steam) {
            $steam = $player->steam;
            if ($steam->profilePrivate !== null) {
                $lines[] = 'Приватность: ' . ($steam->profilePrivate ? 'Профиль закрыт' : 'Профиль открыт');
            }
        }

        $createdAt = $this->formatRustAppTimestamp($player->createdAt, $formatter);
        if ($createdAt) {
            $lines[] = 'Аккаунт создан: ' . Html::encode($createdAt);
        }

        if ($player->lastLanguage) {
            $lines[] = 'Язык в игре: ' . Html::encode($player->lastLanguage);
        }

        if ($player->steamData) {
            $steamData = $player->steamData;
            if ($steamData->rustHoursTotal !== null) {
                $lines[] = 'Часов в RUST: ' . Html::encode((string)$steamData->rustHoursTotal);
            }
            if ($steamData->banData) {
                $vac = $steamData->banData->vacBan && $steamData->banData->vacBan->count !== null ? (int)$steamData->banData->vacBan->count : 0;
                $game = $steamData->banData->gameBan && $steamData->banData->gameBan->count !== null ? (int)$steamData->banData->gameBan->count : 0;
                $lines[] = 'Gamebans / VAC: ' . ($vac || $game ? ("Game: {$game}, VAC: {$vac}") : 'Банов нет');
            }
            if ($steamData->updatedAt) {
                $lastUpdate = $this->formatRustAppTimestamp($steamData->updatedAt, $formatter);
                if ($lastUpdate) {
                    $lines[] = 'Последнее обновление: ' . Html::encode($lastUpdate);
                }
            }
        }

        $lines[] = '';
        $lines[] = '<b>RustApp тиммейты:</b>';

        $teamLines = [];
        if (!empty($rustAppData->team)) {
            foreach ($rustAppData->team as $member) {
                $link = $member->steamId
                    ? '<a href="https://steamcommunity.com/profiles/' . Html::encode($member->steamId) . '">' . Html::encode($member->steamName ?? $member->steamId) . '</a>'
                    : Html::encode($member->steamName ?? '');
                $status = $member->status ? (' (' . Html::encode($member->status) . ')') : '';
                $teamLines[] = '• ' . $link . $status;
            }
        }
        if (!empty($teamLines)) {
            $lines = array_merge($lines, $teamLines);
        } else {
            $lines[] = '• тиммейтов нет';
        }

        return $lines;
    }

    private function formatRustAppTimestamp(?int $timestamp, \yii\i18n\Formatter $formatter): ?string
    {
        if (empty($timestamp)) {
            return null;
        }

        if ($timestamp > 9999999999) {
            $timestamp = (int) floor($timestamp / 1000);
        }

        if ($timestamp <= 0) {
            return null;
        }

        return $formatter->asDatetime($timestamp, 'php:d.m.Y H:i');
    }

    private function buildReportMessage(
        User $sender,
        User $reportUser,
        string $reason,
        int $count,
        float $playHourTotal,
        int $totalPlaytime,
        int $playtimeWipe,
        float $playHourWipe,
        int $kills,
        int $deaths,
        float $kd,
        ?array $countryItem
    ): string {
        $message = "⚔ <b>{$this->serverName}</b>" . PHP_EOL
            . "Отправил: {$sender->username} (<code>{$sender->steam_id}</code>)" . PHP_EOL . PHP_EOL
            . "Подозреваемый: <a href=\"https://steamcommunity.com/profiles/{$reportUser->steam_id}\">{$reportUser->username}</a>" . PHP_EOL
            . "SteamId: <code>{$reportUser->steam_id}</code>" . PHP_EOL
            . "Причина: <b>{$reason}</b>" . PHP_EOL
            . "Кол-во репортов на игрока: <b>{$count}</b>" . PHP_EOL
            . "Играл всего: {$playHourTotal} ч. ({$totalPlaytime} мин)" . PHP_EOL;

        if ($playtimeWipe > 0) {
            $message .= "Играл в текущий вайп: {$playHourWipe} ч. ({$playtimeWipe} мин)" . PHP_EOL;
        }

        if (!empty($countryItem)) {
            $message .= "Страна: {$countryItem['icon']} {$countryItem['name']}" . PHP_EOL;
        }

        $message .= "Килы: {$kills}/{$deaths} (К/Д: {$kd})";

        return $message;
    }

    private function buildRedFlagButtons(User $reportUser, ?Servers $server): array
    {
        $serverId = $server && $server->rust_app_id ? (int)$server->rust_app_id : null;
        $commonPayload = [
            'steam_id' => $reportUser->steam_id,
        ];
        if ($serverId) {
            $commonPayload['server_ids'] = [$serverId];
        }

        $cheatsPayload = $commonPayload;
        $cheatsPayload['action'] = 'ban-cheats';

        $foreignPayload = $commonPayload;
        $foreignPayload['action'] = 'ban-foreign-bans';

        return [
            [
                [
                    'text' => '🔴 Читы',
                    'callback_data' => json_encode($cheatsPayload, JSON_UNESCAPED_UNICODE),
                ],
                [
                    'text' => '🔴 Баны на других проектах',
                    'callback_data' => json_encode($foreignPayload, JSON_UNESCAPED_UNICODE),
                ],
            ],
        ];
    }
}