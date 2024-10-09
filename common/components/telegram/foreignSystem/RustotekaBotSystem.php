<?php

namespace common\components\telegram\foreignSystem;

use backend\models\TelegramConstructorMessage;
use common\components\oauth\Steam;
use common\components\telegram\TelegramApiHelper;
use common\models\box\Box;
use common\models\box\Drop;
use common\models\profit\Profit;
use common\models\servers\Servers;
use common\models\telegram\TelegramMessage;
use common\models\telegram\TelegramUser;
use common\models\user\UserBox;
use common\models\user\UserConfirmCode;
use common\models\user\UserDrop;
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
        return 'prostoj.store';
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

        if (strlen($messageText) === 17 && strlen(preg_replace('/[^0-9]/', "", $messageText)) === 17) {
            return "SteamId: {$messageText}";
        }
        if (Steam::hasLinkProfile($messageText)) {
            $steamId = Steam::getSteamId($messageText);
            if (empty($steamId)) {
                return "⛔ Произошла ошибка, вы неверно указали ссылку на профиль или SteamId.";
            }
            return "SteamId: {$steamId}";
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

    public function getWipe() {
        $cacheKey = 'PersonalBotSystem_getWipe';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        $text = "";
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->andWhere(['status' => Servers::STATUS_ACTIVE])
                          ->all();

        foreach ($servers as $k => $server) {
            $date0 = new \DateTime($server->wipe);
            $date = new \DateTime($server->next_wipe);
            $date2 = new \DateTime($server->global_wipe);
            if ($k > 0) {
                $text .= PHP_EOL . PHP_EOL;
            }
            $name = substr($server->name, strpos($server->name, '['), strripos($server->name, ']'));
            $text .= "<b>{$name}</b>";
            $text .= PHP_EOL . "Последний: <code>{$date0->format('d.m.Y в H:i МСК')}</code>";
            $text .= PHP_EOL . "Следующий: <code>{$date->format('d.m.Y в H:i МСК')}</code>";
            $text .= PHP_EOL . "Глобал: <code>{$date2->format('d.m.Y в H:i МСК')}</code>";
        }

        Yii::$app->cache->set($cacheKey, $text, 60);
        return $text;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function _getStartMessageText($name)
    {
        return "Приветствую{$name}!
Для активации бота перейдите на страницу https://prostoj.store/bot/activate и скопируйте код активации в этот чат.";
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
        return 'https://prostoj.store/api/telegram-personal-bot/' . $method;
    }
}
