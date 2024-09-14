<?php

namespace common\components\telegram\foreignSystem;

use backend\models\TelegramConstructorMessage;
use common\components\telegram\TelegramApiHelper;
use common\models\profit\Profit;
use common\models\servers\Servers;
use common\models\user\UserConfirmCode;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use common\models\user\User;

class PersonalBotSystem extends AbstractSystem
{

    /**
     * @return int
     */
    public function getSystemId()
    {
        return 3;
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

        $answerMessage = null;
        if (strlen($messageText) === 20) {
            $answerMessage = $this->loginUser($message);
        }

        switch ($messageText) {
            case '/help':
                return "<b>/pop</b> - Онлайн на серверах"
                    . PHP_EOL . "<b>/wipe</b> - Календарь вайпов"
                    . PHP_EOL . "<b>/ip</b> - IP серверов";
            case '/pop':
                return $this->getOnline();
            case '/wipe':
                return $this->getWipe();
            case '/ip':
                return $this->getIp();
        }

        return $answerMessage;
    }

    public function getWipe() {
        $cacheKey = 'PersonalBotSystem_getWipe';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        $text = "";
        /** @var Servers[] $servers */
        $servers = Servers::find()
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

    public function getOnline() {
        $cacheKey = 'PersonalBotSystem_getOnline';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        $text = "";
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->all();

        foreach ($servers as $k => $server) {
            $lineSize = 10;
            $pl = $server->players + $server->joined;
            $lineGreen = ceil($lineSize/$server->max * (ceil($pl/10) * 10));
            if ($lineGreen > $lineSize) {
                $lineGreen = $lineSize;
            }
            $lineSize -= $lineGreen;
            if ($k > 0) {
                $text .= PHP_EOL . PHP_EOL;
            }
            $name = substr($server->name, strpos($server->name, '['), strripos($server->name, ']'));
            $text .= "<b>{$name}</b>";
            $text .= PHP_EOL;
            for ($i = 0; $i < $lineGreen; $i++) {
                $text .= "🟩";
            }
            for ($i = 0; $i < $lineSize; $i++) {
                $text .= "⬜️";
            }
            $text .= PHP_EOL . "Онлайн: <code>{$pl}/{$server->max}</code>";
        }

        Yii::$app->cache->set($cacheKey, $text, 60);
        return $text;
    }

    public function getIp() {
        $cacheKey = 'PersonalBotSystem_getIp';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        $text = "";
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->all();

        foreach ($servers as $k => $server) {
            if ($k > 0) {
                $text .= PHP_EOL . PHP_EOL;
            }
            $name = substr($server->name, strpos($server->name, '['), strripos($server->name, ']'));
            $text .= "<b>{$name}</b>";
            $text .= PHP_EOL . "<code>connect {$server->ip}:{$server->port}</code>";
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
        return "Приветствую, {$name}!
Для активации бота перейдите на страницу https://prostoj.store/bot/activate и скопируйте код активации в этот чат.";
    }

    /**
     * @return TelegramApiHelper
     */
    public function getTelegramBot()
    {
        return Yii::$app->personalBotTelegram;
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
        return 'Команда не найдена, попробуйте другую 😏';
    }

    /**
     * @param array $message
     *
     * @return string
     */
    public function loginUser($message)
    {
        $chatId = ArrayHelper::getValue($message, 'chat.id');

        $code     = $this->_getMessageText($message);
        $user = $this->getUserByAuthCode($code, $chatId);

        $answerMessage = 'Ошибка авторизации, код неверен! 🤔';

        if (!empty($user)) {
            if (!empty($user->telegram_chat_id)) {
                return 'Вы уже авторизованы!';
            }
            $user->telegram_chat_id = $chatId;
            $user->save();
            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_TELEGRAM_BOT;
            $profit->amount = 50;
            $profit->user_balance_id = $user->getPersonalBalance()->id;
            $profit->comment = Yii::t('common', 'Бонус за привязку телеграм бота','ru-RU');
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);
            $answerMessage = $this->_getAfterRegisterMessage($user);
        }

        return $answerMessage;
    }

    /**
     * @param string $code
     * @param int    $chatId
     *
     * @return User
     */
    public function getUserByAuthCode($code, $chatId)
    {
        if (empty($code)) {
            return null;
        }
        $user = UserConfirmCode::getUserByTelegramCode($code);
        if (empty($user)) {
            return null;
        }

        UserConfirmCode::updateStatus($user->id, UserConfirmCode::TYPE_TELEGRAM_BOT);
        return $user;
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
     * @param User $user
     *
     * @return string|array
     */
    protected function _getAfterRegisterMessage($user)
    {
        return "✅ {$user->username}, Вы успешно авторизовались!"
            . PHP_EOL . "🎁 Вам начислен бонус <b>50 РУБ</b> на сайте!"
            . PHP_EOL . PHP_EOL . "ℹ️Напишите /help, чтобы увидеть команды для бота.";
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
