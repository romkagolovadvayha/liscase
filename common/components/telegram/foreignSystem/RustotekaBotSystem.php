<?php

namespace common\components\telegram\foreignSystem;

use backend\models\TelegramConstructorMessage;
use common\components\oauth\Steam;
use common\components\telegram\TelegramApiHelper;
use common\models\bansystem\BanList;
use common\models\box\Box;
use common\models\box\Drop;
use common\models\profit\Profit;
use common\models\servers\Servers;
use common\models\telegram\TelegramMessage;
use common\models\telegram\TelegramUser;
use common\models\user\UserBox;
use common\models\user\UserConfirmCode;
use common\models\user\UserDrop;
use DemonDogSL\translateManager\models\Language;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use common\models\user\User;

class RustotekaBotSystem extends AbstractSystemBots
{

    /**
     * @return int
     */
    public function getSystemId()
    {
        return 4;
    }

    /**
     * @return string
     */
    public function getSystemName()
    {
        return Yii::$app->settings->get('site_domain');
    }

    /**
     * @param array $message
     *
     * @return null|string
     */
    public function executeInnerCommand($message)
    {
        $messageText = $this->_getMessageText($message);
        $chatId = ArrayHelper::getValue($message, 'chat.id');

        $cacheKey = "request_kd_{$chatId}";
        if (Yii::$app->cache->get($cacheKey)) {
            $seconds = Yii::$app->cache->get($cacheKey) - time();
            $secondsWord = $this->numDecline($seconds, 'секунда, секунды, секунд', false);
            return "⛔ Вы делаете запросы слишком часто, попробуйте через <b>{$seconds}</b> {$secondsWord}.";
        }
        Yii::$app->cache->set($cacheKey, time() + 10, 10);

        if (strlen($messageText) === 17 && strlen(preg_replace('/[^0-9]/', "", $messageText)) === 17) {
            return $this->getCheck($messageText);
        }
        if (Steam::hasLinkProfile($messageText)) {
            $steamId = Steam::getSteamId($messageText);
            if (empty($steamId)) {
                return "⛔ Произошла ошибка, вы неверно указали ссылку на профиль или SteamId.";
            }
            return $this->getCheck($steamId);
        }

        TelegramMessage::createModel($chatId, $messageText);
//        switch ($messageText) {
//            case '/help':
//                return "<b>/pop</b> - Онлайн на серверах"
//                    . PHP_EOL . "<b>/wipe</b> - Календарь вайпов"
//                    . PHP_EOL . "<b>/bonus</b> - Получить ежедневный бонус"
//                    . PHP_EOL . "<b>/ip</b> - IP серверов";
//            case '/pop':
//                return $this->getOnline();
//            case '/wipe':
//                return $this->getWipe();
//            case '/ip':
//                return $this->getIp();
//            case '/bonus':
//                return $this->getBonus($message);
//        }

        return null;
    }

    private function numDecline( $number, $titles, $show_number = true ) {
        if( is_string( $titles ) ){
            $titles = preg_split( '/, */', $titles );
        }

        // когда указано 2 элемента
        if( empty( $titles[2] ) ){
            $titles[2] = $titles[1];
        }

        $cases = [ 2, 0, 1, 1, 1, 2 ];

        $intnum = abs( (int) strip_tags( $number ) );

        $title_index = ( $intnum % 100 > 4 && $intnum % 100 < 20 )
            ? 2
            : $cases[ min( $intnum % 10, 5 ) ];

        return ( $show_number ? "$number " : '' ) . $titles[ $title_index ];
    }

    private function flags() {
        return [
          'ru' => [
              'icon' => '🇷🇺',
              'name' => 'Россия',
          ],
          'by' => [
              'icon' => '🇧🇾',
              'name' => 'Баларусь',
          ],
          'kz' => [
              'icon' => '🇰🇿',
              'name' => 'Казахстан',
          ],
          'ua' => [
              'icon' => '🇺🇦',
              'name' => 'Украина',
          ],
        ];
    }

    public function getCheck($steamId) {
        $message = "<b>Информация о игроке</b>";

        try {
            $userInfo = Steam::getInfoUser($steamId);
            if (!empty($userInfo) && !empty($userInfo[0])) {
                if (!empty($userInfo[0]['personaname'])) {
                    $message .=  PHP_EOL . "Ник: {$userInfo[0]['personaname']}";
                }
                if (!empty($userInfo[0]['loccountrycode'])) {
                    $countryName = "";
                    $flagItem = $this->flags()[strtolower($userInfo[0]['loccountrycode'])];
                    if (!empty($flagItem)) {
                        $countryName = "{$flagItem['icon']} {$flagItem['name']}";
                    } else {
                        $countryName = $userInfo[0]['loccountrycode'];
                    }
                    if (empty($flagItem)) {
                        $language = Language::find()
                                            ->andWhere(['country' => mb_strtolower($userInfo[0]['loccountrycode'])])
                                            ->one();
                        $message .=  PHP_EOL . "Страна: {$language->name_ascii}";
                    } else {
                        $message .=  PHP_EOL . "Страна: {$countryName}";
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::$app->telegramReports->sendMessage("RustotekaBotSystem:" . $e->getLine() . ":" . $e->getMessage());
        }

        $message .=  PHP_EOL . "SteamId: <a href=\"https://steamcommunity.com/profiles/{$steamId}\">{$steamId}</a>";

        try {
            $games = Steam::getGameInfo($steamId);
            foreach ($games as $game) {
                if ($game['appid'] == 252490) {
                    $hours = $game['playtime_forever'];
                    $message .=  PHP_EOL . "Часов в Steam: " . $hours;
                    break;
                }
            }
        } catch (\Exception $e) {
            Yii::$app->telegramReports->sendMessage("SaveStatsJob:" . $e->getLine() . ":" . $e->getMessage());
        }

        /** @var BanList[] $banList */
        $banList = BanList::find()
            ->andWhere(['steam_id' => $steamId])
            ->orderBy(['banned_at' => SORT_DESC])
            ->all();


        if (empty($banList)) {
            $message .=  PHP_EOL . PHP_EOL . "Аккаунт чист, ни одного бана игрока не найдено!";
        } else {
            $message .=  PHP_EOL . PHP_EOL . "<b>Баны игрока:</b>";
        }

        foreach ($banList as $item) {
            $bannedAt = new \DateTime($item->banned_at);
            $unBannedAt = "Никогда";
            $label = "";
            if (!empty($item->unbanned_at)) {
                $date = new \DateTime($item->unbanned_at);
                $unBannedAt = $date->format('d.m.Y H:i:s');
                if ($date->getTimestamp() < time()) {
                    $label = " <i>(Бан снят)</i>";
                }
            }
            $serverName = $item->server_name;
            if (empty($serverName)) {
                $serverName = "Бан на всех серверах проекта.";
            }
            $message .= PHP_EOL . PHP_EOL . "Сервер: <b>{$item->project_name}</b> - {$serverName}" . $label;
            $message .= PHP_EOL . "Дата бана: {$bannedAt->format('d.m.Y H:i:s')}";
            $message .= PHP_EOL . "Дата разбана: {$unBannedAt}";
            $message .= PHP_EOL . "Причина: {$item->reason}";
        }

        return $message;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function _getStartMessageText($name)
    {
        return "Приветствую{$name}!
Чтобы проверить игрока, просто укажите ссылку на профиль или SteamID.";
    }

    /**
     * @return TelegramApiHelper
     */
    public function getTelegramBot()
    {
        return Yii::$app->rustotekaBotTelegram;
    }

    /**
     * @param int    $chatId
     * @param string $buttonValue
     *
     * @return null|string|array
     */
    public function executeCallBack($chatId, $buttonValue)
    {
        /*if (!empty($buttonValue) && strpos($buttonValue, 'messageId') !== false) {
            $data = json_decode($buttonValue, 1);
            $response = $this->getMessage($data['messageId'], $data['current_language']);
            if (!empty($response) && !empty($response['message'])) {
                return [
                    'message' => $response['message'],
                    'buttons' => $response['buttons'],
                ];
            }
        }*/
        return '⛔ Команда не найдена, попробуйте другую';
    }

    /**
     * @param $message
     *
     * @throws \Exception
     */
    public function loginUser($message)
    {
        $chatId = ArrayHelper::getValue($message, 'chat.id');
        $lastName  = ArrayHelper::getValue($message, 'chat.last_name') ?? '';
        $firstName = ArrayHelper::getValue($message, 'chat.first_name') ?? '';
        $username = ArrayHelper::getValue($message, 'chat.username') ?? '';
        $name = '';
        if (!empty($firstName) || !empty($lastName)) {
            $name = trim($lastName . ' ' . $firstName);
        }

        TelegramUser::createModel($name, $chatId, $username, TelegramUser::TYPE_RUSTOTEKA);
    }

    /**
     * @param int $messageId
     * @param string    $language
     *
     * @return array
     */
    public function getMessage($messageId, $language)
    {
        if (empty($messageId)) {
            return [];
        }
        if (empty($language)) {
            return [];
        }
        $cacheKey = "actionGetMessage_{$messageId}_{$language}";
        $cacheData = Yii::$app->cache->get($cacheKey);
        if (!empty($cacheData)) {
            return $cacheData;
        }
        $model = TelegramConstructorMessage::findOne($messageId);
        $message = $model->getTelegramMessage($language);
        $buttons = $model->getTelegramButtons($language);

        $result = [
            'message' => $message,
            'buttons'    => $buttons,
        ];

        Yii::$app->cache->set($cacheKey, $result, 60);
        return $result;
    }

    /**
     * @param $method
     *
     * @return string
     */
    protected function _getUrl($method)
    {
        return 'https://' . Yii::$app->settings->get('site_domain') . '/api/telegram-personal-bot/' . $method;
    }
}
