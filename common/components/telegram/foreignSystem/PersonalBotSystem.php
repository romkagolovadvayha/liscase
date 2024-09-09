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
                return "<b>/pop</b> - Информация об онлайне на сервере"
                    . "<b>/wipe</b> - Информация о вайпах на серверах";
            case '/pop':
                return $this->getOnline();
            case '/wipe':
                return $this->getWipe();
        }

        return $answerMessage;
    }

    public function getWipe() {
        $text = "<b>Вайп на серверах:</b>" . PHP_EOL;
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->all();

        foreach ($servers as $server) {
            $date = new \DateTime($server->next_wipe);
            $text .= PHP_EOL . "{$server->name} - Следующий вайп: <b>{$date->format('d.m.Y в H:i:s МСК')}</b>";
        }

        return $text;
    }

    public function getOnline() {
        $text = "<b>Онлайн на серверах:</b>" . PHP_EOL;
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->all();

        foreach ($servers as $server) {
            $pl = $server->players + $server->joined;
            $text .= PHP_EOL . "{$server->name} - Текущий онлайн: <b>{$pl}</b>";
        }

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
        $userInfo = $this->getUserByAuthCode($code, $chatId);

        $answerMessage = 'Ошибка авторизации, код неверен! 🤔';

        if (!empty($userInfo)) {
            $systemId = $this->getSystemId();
            $userId   = ArrayHelper::getValue($userInfo, 'userId');
            $language   = ArrayHelper::getValue($userInfo, 'current_language');
            if (!empty($userId)) {
                $user = User::findOne($userId);
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
                $answerMessage = $this->_getAfterRegisterMessage(ArrayHelper::getValue($userInfo, 'email'), $language);
            }
        }

        return $answerMessage;
    }

    /**
     * @param string $code
     * @param int    $chatId
     *
     * @return array
     */
    public function getUserByAuthCode($code, $chatId)
    {
        if (empty($code)) {
            return [];
        }
        $user = UserConfirmCode::getUserByTelegramCode($code);
        if (empty($user)) {
            return [];
        }

        UserConfirmCode::updateStatus($user->id, UserConfirmCode::TYPE_TELEGRAM_BOT);
        return [
            'userId' => $user->id,
            'email'  => $user->email,
            'current_language'  => $user->current_language,
        ];
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
     * @param string $login
     *
     * @return string|array
     */
    protected function _getAfterRegisterMessage($login, $language = null)
    {
        /*$response = $this->getMessage(3, $language);
        if (!empty($response) && !empty($response['message'])) {
            return [
                'message' => $response['message'],
                'buttons' => $response['buttons'],
            ];
        }*/
        return "✅ Вы успешно авторизовались!"
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
