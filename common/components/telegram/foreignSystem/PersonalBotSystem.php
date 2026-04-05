<?php

namespace common\components\telegram\foreignSystem;

use backend\models\TelegramConstructorMessage;
use common\components\telegram\TelegramApiHelper;
use common\models\box\Box;
use common\models\box\Drop;
use common\models\profit\Profit;
use common\models\servers\Servers;
use common\models\statistics\Chats;
use common\models\user\UserBox;
use common\models\user\UserConfirmCode;
use common\models\user\UserDrop;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use common\models\user\User;

/**
 * Личный бот Telegram. Паритет команд для ВКонтакте — в {@see \common\components\vk\VkBotSystem}
 * (вебхук {@see \api\controllers\VkController}).
 */
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
     * @return TelegramApiHelper
     */
    public function getTelegramBot()
    {
        return (clone Yii::$app->rustotekaBotTelegram)
            ->setToken($this->getTelegramToken());
    }

    /**
     * @return string
     */
    public function getTelegramToken()
    {
        return Yii::$app->settings->get('tgbot_botToken');
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

        $answerMessage = null;
        if (strlen($messageText) === 20) {
            $answerMessage = $this->loginUser($message);
        }

        switch ($messageText) {
            case '/help':
                return "📋 <b>Доступные команды:</b>"
                    . PHP_EOL . PHP_EOL
                    . "👥 <b>/pop</b> — Онлайн на серверах"
                    . PHP_EOL . "📅 <b>/wipe</b> — Календарь вайпов"
                    . PHP_EOL . "🎁 <b>/bonus</b> — Получить ежедневный бонус"
                    . PHP_EOL . "🔗 <b>/ip</b> — IP-адреса серверов"
                    . PHP_EOL . "💰 <b>/balance</b> — Баланс аккаунта"
                    . PHP_EOL . PHP_EOL
                    . "⚙️ <b>Настройки уведомлений:</b>"
                    . PHP_EOL . "🚨 <b>/raid_alert</b> — Оповещения о рейдах"
                    . PHP_EOL . "🔔 <b>/ban_alert</b> — Оповещения о банах"
                    . PHP_EOL . PHP_EOL
                    . "🔓 <b>/unlink</b> — Отвязать Telegram от аккаунта";
            case '/pop':
                return $this->getOnline();
            case '/wipe':
                return $this->getWipe();
            case '/ip':
                return $this->getIp();
            case '/bonus':
                return $this->getBonus($message);
            case '/balance':
                return $this->getBalance($message);
            case '/raid_alert':
                return $this->getRaidAlert($message);
            case '/ban_alert':
                return $this->getBanAlert($message);
            case '/unlink':
                return $this->unlinkAccount($message);
        }

        return $answerMessage;
    }

    /**
     * @param Servers $server
     * @return string
     */
    private function getServerName($server)
    {
        $wipeTypeLabel = '';
        if ($server->wipe_type === 7) {
            $wipeTypeLabel = 'Недельный';
        } elseif ($server->wipe_type === 14) {
            $wipeTypeLabel = 'Двухнедельный';
        } elseif ($server->wipe_type === 30) {
            $wipeTypeLabel = 'Месячный';
        }
        return '[' . Yii::t('database', $server->monitoring_name) . '] | ' . $wipeTypeLabel;
    }

    public function getWipe() {
        $cacheKey = 'PersonalBotSystem_getWipe';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        $text = "📅 <b>Календарь вайпов</b>" . PHP_EOL;
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->andWhere(['status' => Servers::STATUS_ACTIVE])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        foreach ($servers as $k => $server) {
            $date0 = new \DateTime($server->wipe);
            $date = new \DateTime($server->next_wipe);
            $date2 = new \DateTime($server->global_wipe);
            if ($k > 0) {
                $text .= PHP_EOL;
            }
            $name = $this->getServerName($server);
            $text .= PHP_EOL . "🖥️ <b>{$name}</b>";
            $text .= PHP_EOL . "   ⏮️ Последний: <code>{$date0->format('d.m.Y в H:i')} МСК</code>";
            $text .= PHP_EOL . "   ⏭️ Следующий: <code>{$date->format('d.m.Y в H:i')} МСК</code>";
            $text .= PHP_EOL . "   🌍 Глобал: <code>{$date2->format('d.m.Y в H:i')} МСК</code>";
        }

        Yii::$app->cache->set($cacheKey, $text, 60);
        return $text;
    }

    public function getRaidAlert($message) {
        $chatId = ArrayHelper::getValue($message, 'chat.id');
        $cacheKey = 'PersonalBotSystem_getRaidAlert_' . $chatId;
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        $user = User::findByChatId($chatId);
        if (empty($user)) {
            $return = '🔒 <b>Требуется авторизация</b>' 
                . PHP_EOL . PHP_EOL
                . "Для использования уведомлений необходимо авторизоваться." 
                . PHP_EOL . "Напишите <code>/start</code> для начала.";
            return $return;
        }
        if ($user->status === User::STATUS_BLOCKED) {
            $return = '🚫 <b>Доступ запрещен</b>' 
                . PHP_EOL . PHP_EOL
                . "Ваш аккаунт заблокирован.";
            Yii::$app->cache->set($cacheKey, $return, 60);
            return $return;
        }

        Yii::$app->cache->set($cacheKey, "⏳ Вы пытаетесь использовать команду слишком часто, попробуйте чуть позже!", 10);
        if ($user->raid_notify) {
            $user->raid_notify = 0;
            $user->save();
            return "🔕 <b>Уведомления отключены</b>" 
                . PHP_EOL . PHP_EOL
                . "Оповещения о рейдах успешно отключены.";
        } else {
            $user->raid_notify = 1;
            $user->save();
            return "🔔 <b>Уведомления включены</b>" 
                . PHP_EOL . PHP_EOL
                . "Теперь вы будете получать оповещения о рейдах на ваших базах.";
        }
    }

    /**
     * Отвязать Telegram-бота от аккаунта пользователя.
     * @param array $message
     * @return string
     */
    public function unlinkAccount($message)
    {
        $chatId = ArrayHelper::getValue($message, 'chat.id');
        $cacheKey = 'PersonalBotSystem_unlink_' . $chatId;
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }

        $user = User::findByChatId($chatId);
        if (empty($user)) {
            $return = '🔒 <b>Требуется авторизация</b>'
                . PHP_EOL . PHP_EOL
                . "Telegram не привязан к аккаунту. Для привязки напишите <code>/start</code>.";
            return $return;
        }

        if ($user->status === User::STATUS_BLOCKED) {
            $return = '🚫 <b>Доступ запрещен</b>'
                . PHP_EOL . PHP_EOL
                . "Ваш аккаунт заблокирован.";
            Yii::$app->cache->set($cacheKey, $return, 60);
            return $return;
        }

        Yii::$app->cache->set($cacheKey, "⏳ Попробуйте через минуту.", 60);

        $user->telegram_chat_id = null;
        $user->is_telegram_blocked = false;
        $user->save(false);

        return '✅ <b>Telegram отвязан</b>'
            . PHP_EOL . PHP_EOL
            . "Бот отключен от аккаунта <b>{$user->username}</b>. Для повторной привязки напишите <code>/start</code> и следуйте инструкциям.";
    }

    public function getBanAlert($message) {
        $chatId = ArrayHelper::getValue($message, 'chat.id');
        $cacheKey = 'PersonalBotSystem_getBanAlert_' . $chatId;
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        $user = User::findByChatId($chatId);
        if (empty($user)) {
            $return = '🔒 <b>Требуется авторизация</b>' 
                . PHP_EOL . PHP_EOL
                . "Для использования уведомлений необходимо авторизоваться." 
                . PHP_EOL . "Напишите <code>/start</code> для начала.";
            return $return;
        }
        if ($user->status === User::STATUS_BLOCKED) {
            $return = '🚫 <b>Доступ запрещен</b>' 
                . PHP_EOL . PHP_EOL
                . "Ваш аккаунт заблокирован.";
            Yii::$app->cache->set($cacheKey, $return, 60);
            return $return;
        }

        Yii::$app->cache->set($cacheKey, "⏳ Вы пытаетесь использовать команду слишком часто, попробуйте чуть позже!", 10);
        if ($user->ban_notify) {
            $user->ban_notify = 0;
            $user->save();
            return "🔕 <b>Уведомления отключены</b>" 
                . PHP_EOL . PHP_EOL
                . "Оповещения о банах успешно отключены.";
        } else {
            $user->ban_notify = 1;
            $user->save();
            return "🔔 <b>Уведомления включены</b>" 
                . PHP_EOL . PHP_EOL
                . "Теперь вы будете получать оповещения о банах игроков, на которых вы отправили жалобу.";
        }
    }

    public function getOnline() {
        $cacheKey = 'PersonalBotSystem_getOnline';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        $text = "👥 <b>Онлайн на серверах</b>" . PHP_EOL;
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->andWhere(['status' => Servers::STATUS_ACTIVE])
                          ->orderBy(['sort' => SORT_ASC])
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
                $text .= PHP_EOL;
            }
            $name = $this->getServerName($server);
            $text .= PHP_EOL . "🖥️ <b>{$name}</b>";
            $text .= PHP_EOL;
            for ($i = 0; $i < $lineGreen; $i++) {
                $text .= "🟩";
            }
            for ($i = 0; $i < $lineSize; $i++) {
                $text .= "⬜️";
            }
            $percentage = round(($pl / $server->max) * 100);
            $text .= PHP_EOL . "   👤 <code>{$pl}</code>/<code>{$server->max}</code> ({$percentage}%)";
        }

        Yii::$app->cache->set($cacheKey, $text, 60);
        return $text;
    }

    public function getIp() {
        $cacheKey = 'PersonalBotSystem_getIp';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        $text = "🔗 <b>IP-адреса серверов</b>" . PHP_EOL;
        /** @var Servers[] $servers */
        $servers = Servers::find()
                          ->andWhere(['status' => Servers::STATUS_ACTIVE])
                          ->orderBy(['sort' => SORT_ASC])
                          ->all();

        foreach ($servers as $k => $server) {
            if ($k > 0) {
                $text .= PHP_EOL;
            }
            $name = $this->getServerName($server);
            $text .= PHP_EOL . "🖥️ <b>{$name}</b>";
            $text .= PHP_EOL . "   📍 <code>connect {$server->ip}:{$server->port}</code>";
        }

        Yii::$app->cache->set($cacheKey, $text, 60);
        return $text;
    }

    public function getBalance($message) {
        $chatId = ArrayHelper::getValue($message, 'chat.id');
        $cacheKey = 'PersonalBotSystem_getBalance_' . $chatId;
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        
        $user = User::findByChatId($chatId);
        if (empty($user)) {
            $return = '🔒 <b>Требуется авторизация</b>' 
                . PHP_EOL . PHP_EOL
                . "Для просмотра баланса необходимо авторизоваться." 
                . PHP_EOL . "Напишите <code>/start</code> для начала.";
            return $return;
        }
        
        if ($user->status === User::STATUS_BLOCKED) {
            $return = '🚫 <b>Доступ запрещен</b>' 
                . PHP_EOL . PHP_EOL
                . "Ваш аккаунт заблокирован.";
            Yii::$app->cache->set($cacheKey, $return, 60);
            return $return;
        }
        
        $personalBalance = $user->getPersonalBalance();
        $skinsBalance = $user->getSkinsBalance();
        
        $text = "💰 <b>Баланс аккаунта</b>" 
            . PHP_EOL
            . "👤 Пользователь: <b>{$user->username}</b>" 
            . PHP_EOL . PHP_EOL
            . "💳 Лицевой счет:" 
            . PHP_EOL . "   <code>{$personalBalance->getBalanceFormat()}</code> РУБ" 
            . PHP_EOL . PHP_EOL
            . "🎨 Скины:" 
            . PHP_EOL . "   <code>{$skinsBalance->getBalanceFormat()}</code> РУБ";
        
        $domain = Yii::$app->settings->get('site_domain');
        $text .= PHP_EOL . PHP_EOL
            . "🔗 <a href=\"https://{$domain}\">Перейти в магазин</a>";
        
        Yii::$app->cache->set($cacheKey, $text, 30);
        return $text;
    }

    public function getBonus($message) {
        $chatId = ArrayHelper::getValue($message, 'chat.id');
        $cacheKey = 'PersonalBotSystem_getBonus_' . $chatId;
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        $user = User::findByChatId($chatId);
        if (empty($user)) {
            $return = '🔒 <b>Требуется авторизация</b>' 
                . PHP_EOL . PHP_EOL
                . "Для получения ежедневного бонуса необходимо авторизоваться." 
                . PHP_EOL . "Напишите <code>/start</code> для начала.";
            return $return;
        }
        if ($user->is_telegram_blocked) {
            $user->is_telegram_blocked = false;
            $user->save(false);
        }
        if ($user->status === User::STATUS_BLOCKED) {
            $return = '🚫 <b>Доступ запрещен</b>' 
                . PHP_EOL . PHP_EOL
                . "Ваш аккаунт заблокирован.";
            Yii::$app->cache->set($cacheKey, $return, 60);
            return $return;
        }
        $box = Box::findOne(14);
        $nextOpenFreeBoxDate = Box::getNextOpenFreeBoxDate($user->id);
        if (!empty($nextOpenFreeBoxDate)) {
            $date = new \DateTime($nextOpenFreeBoxDate);
            $return = '⏰ <b>Бонус уже получен</b>' 
                . PHP_EOL . PHP_EOL
                . "Вы уже получили награду сегодня." 
                . PHP_EOL . "Следующий бонус будет доступен:" 
                . PHP_EOL . "<code>{$date->format('d.m.Y в H:i')} МСК</code>";
            Yii::$app->cache->set($cacheKey, $return, 60);
            return $return;
        }
        $userBoxId = UserBox::createRecord($user->id, $box->id);
        $userBox = UserBox::findOne($userBoxId);
        [$boxDropCarousel, $number] = $userBox->box->_getDropFinal();
        $userBox->status = UserBox::STATUS_OPENED;
        $userBox->save();

        /** @var Drop $drop */
        $dropName =  Yii::t('database', $boxDropCarousel[$number]['boxDrop']->drop->name);
        $dropCount =  $boxDropCarousel[$number]['count'];
        //        $dropImage =  $boxDropCarousel[$number]['boxDrop']->drop->imageOrig->getImagePubUrl();

        if ($boxDropCarousel[$number]['boxDrop']->drop->id != 843) {
            UserDrop::createRecord($user->id, $boxDropCarousel[$number]['boxDrop']->drop->id, $box->id, null,UserDrop::STATUS_ACTIVE, false, $boxDropCarousel[$number]['count']);
        } else {
            $userBalance = $user->getPersonalBalance();
            $profit = new Profit();
            $profit->status = 1;
            $profit->type = Profit::TYPE_SELL_DROP;
            $profit->amount = $boxDropCarousel[$number]['count'];
            $profit->user_balance_id = $userBalance->id;
            $profit->comment = Yii::t('common', 'Выигрыш в бесплатной рулетке', [], 'ru-RU');
            $profit->created_at = date('Y-m-d H:i:s');
            $profit->save(false);
        }

        return "🎉 <b>Поздравляем!</b>" 
            . PHP_EOL . PHP_EOL
            . "Вы успешно получили награду:" 
            . PHP_EOL . "🎁 <b>{$dropName}</b> × <code>{$dropCount}</code>";
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function _getStartMessageText($name)
    {
        $domain = Yii::$app->settings->get('site_domain');
        return "👋 Привет{$name}!" 
            . PHP_EOL . PHP_EOL
            . "Для активации бота выполните следующие шаги:" 
            . PHP_EOL . PHP_EOL
            . "1️⃣ Перейдите на страницу:" 
            . PHP_EOL . "<code>https://{$domain}/bot/activate</code>" 
            . PHP_EOL . PHP_EOL
            . "2️⃣ Скопируйте код активации и отправьте его в этот чат.";
    }

    /**
     * @param int    $chatId
     * @param string $buttonValue
     *
     * @return null|string|array
     */
    public function executeCallBack($chatId, $buttonValue)
    {
        try {
            if (!empty($buttonValue)) {
                $buttonValueObj = json_decode($buttonValue, 1);
                if (!empty($buttonValueObj)) {
                    $action = ArrayHelper::getValue($buttonValueObj, 'action');
                    if (!empty($action) && $action == 'mute') {
                      return Chats::actionMute($buttonValueObj);
                    }
                    if (!empty($action) && $action == 'success-skin') {
                      return Chats::actionSuccessSkin($buttonValueObj);
                    }
                    if (!empty($action) && $action == 'reject-skin') {
                      return Chats::actionRejectSkin($buttonValueObj);
                    }
                    if (!empty($action) && $action == 'success-video') {
                      return Chats::actionSuccessVideo($buttonValueObj);
                    }
                    if (!empty($action) && $action == 'reject-video') {
                      return Chats::actionRejectVideo($buttonValueObj);
                    }
                    if (!empty($action) && $action == 'success-building') {
                      return Chats::actionSuccessBuilding($buttonValueObj);
                    }
                    if (!empty($action) && $action == 'reject-building') {
                      return Chats::actionRejectbuilding($buttonValueObj);
                    }
                    if (!empty($action) && $action == 'success-track') {
                      return Chats::actionSuccessTrack($buttonValueObj);
                    }
                    if (!empty($action) && $action == 'reject-track') {
                      return Chats::actionRejectTrack($buttonValueObj);
                    }
                    if (!empty($action) && $action === 'ban-cheats') {
                      return $this->actionBanPlayer($buttonValueObj, 'Читы');
                    }
                    if (!empty($action) && $action === 'ban-foreign-bans') {
                      return $this->actionBanPlayer($buttonValueObj, 'Баны на других проектах');
                    }
                }
            }
        } catch (\Exception $e) {

        }
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
        return '❓ <b>Команда не найдена</b>' 
            . PHP_EOL . PHP_EOL
            . "Попробуйте другую команду или напишите <code>/help</code> для списка доступных команд.";
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

        $answerMessage = '❌ <b>Ошибка авторизации</b>' 
            . PHP_EOL . PHP_EOL
            . "Код активации неверен. Проверьте правильность кода и попробуйте еще раз.";

        if (!empty($user) && !empty($chatId)) {

            if ($user->is_telegram_blocked) {
                $user->is_telegram_blocked = false;
                $user->save(false);
            }

            if (!empty($user->telegram_chat_id)) {
                return 'ℹ️ <b>Уже авторизован</b>' 
                    . PHP_EOL . PHP_EOL
                    . "Вы уже авторизованы в этом боте.";
            }
            $userTG = User::findByChatId($chatId);
            if (!empty($userTG)) {
                return '⚠️ <b>Telegram уже привязан</b>' 
                    . PHP_EOL . PHP_EOL
                    . "С этого Telegram-аккаунта вы уже авторизованы под пользователем <b>{$userTG->username}</b>.";
            }
            $user->telegram_chat_id = $chatId;
            $user->save(false);
            if (!empty($user->getErrors())) {
                $error = "File: PersonalBotSystem"
                    . PHP_EOL . "Method: loginUser"
                    . PHP_EOL . json_encode($user->getErrors());
                Yii::$app->telegramChats->sendMessage($error);
            }
            $existProfit = Profit::find()
                                 ->andWhere(['user_balance_id' => $user->getPersonalBalance()->id])
                                 ->andWhere(['type' => Profit::TYPE_TELEGRAM_BOT])
                                 ->exists();
            if (!$existProfit) {
                $profit                  = new Profit();
                $profit->status          = 1;
                $profit->type            = Profit::TYPE_TELEGRAM_BOT;
                $profit->amount          = 50;
                $profit->user_balance_id = $user->getPersonalBalance()->id;
                $profit->comment         = Yii::t('common', 'Бонус за привязку телеграм бота', 'ru-RU');
                $profit->created_at      = date('Y-m-d H:i:s');
                $profit->save(false);
                $answerMessage = $this->_getAfterRegisterMessage($user);
            } else {
                $answerMessage = '✅ <b>Бот снова привязан</b>'
                    . PHP_EOL . PHP_EOL
                    . "Добро пожаловать, <b>{$user->username}</b>! Привязка восстановлена. Бонус за привязку начисляется только при первой активации."
                    . PHP_EOL . PHP_EOL
                    . "💡 Напишите <code>/help</code> для списка команд.";
            }
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

    private function actionBanPlayer(array $buttonValueObj, string $reason)
    {
        $steamId = ArrayHelper::getValue($buttonValueObj, 'steam_id');
        if (empty($steamId)) {
            return '❌ <b>Ошибка</b>' 
                . PHP_EOL . PHP_EOL
                . "Не удалось определить игрока.";
        }

        $options = [];
        $serverIds = ArrayHelper::getValue($buttonValueObj, 'server_ids', []);
        if (is_array($serverIds) && !empty($serverIds)) {
            $options['server_ids'] = array_values(array_filter(array_map('intval', $serverIds)));
        }

        try {
            $result = Yii::$app->rustApp->createBan($steamId, $reason, $options);
            if (empty($result['success'])) {
                $message = ArrayHelper::getValue($result, 'message', 'Неизвестная ошибка');
                return '❌ <b>Ошибка бана</b>' 
                    . PHP_EOL . PHP_EOL
                    . "Не удалось забанить игрока:" 
                    . PHP_EOL . "<code>{$message}</code>";
            }
        } catch (\Throwable $throwable) {
            Yii::error('Failed to ban via RustApp: ' . $throwable->getMessage(), __METHOD__);
            return '⚠️ <b>Ошибка подключения</b>' 
                . PHP_EOL . PHP_EOL
                . "Ошибка при обращении к игровому серверу. Попробуйте позже.";
        }

        return '✅ <b>Игрок забанен</b>' 
            . PHP_EOL . PHP_EOL
            . "Причина: <code>{$reason}</code>";
    }

    /**
     * @param User $user
     *
     * @return string|array
     */
    protected function _getAfterRegisterMessage($user)
    {
        return "✅ <b>Авторизация успешна!</b>" 
            . PHP_EOL . PHP_EOL
            . "Добро пожаловать, <b>{$user->username}</b>!" 
            . PHP_EOL . PHP_EOL
            . "🎁 Вам начислен приветственный бонус:" 
            . PHP_EOL . "<b>50 РУБ</b> на ваш баланс" 
            . PHP_EOL . PHP_EOL
            . "💡 Напишите <code>/help</code>, чтобы увидеть все доступные команды.";
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
