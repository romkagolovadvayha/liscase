<?php

namespace common\components\queue\support;

use common\components\queue\telegram\SendMessageJob;
use common\models\signs\Signs;
use common\models\servers\Servers;
use common\models\support\SupportMessage;
use common\models\user\User;
use common\models\user\UserRaid;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class BeforeMessageJob extends BaseObject implements JobInterface
{
    public $chatId;
    public $userId;
    public $username;
    public $message;
    public $chatNumber;

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        Yii::$app->telegramChats->sendMessage("BeforeMessageJob Start");
        Yii::$app->telegramChats->sendMessage($this->chatNumber);
        Yii::$app->telegramChats->sendMessage($this->username);
        Yii::$app->telegramChats->sendMessage($this->message);
        try {
            $domain = Yii::$app->settings->get('site_domain');
            $text = "💬 Новое сообщение.";
            $text .= PHP_EOL. "Имя: {$this->username}";
            $text .= PHP_EOL. "Сообщение: {$this->message}";
            $text .= PHP_EOL. "<a href=\"https://{$domain}/support/ticket?id={$this->chatNumber}\">Перейти к тикету</a>";
            Yii::$app->telegramSupport->sendMessage($text);
        } catch (\Exception $e) {
            Yii::$app->telegramChats->sendMessage("BeforeMessageJob: " . $e->getLine() . ":" . $e->getMessage());
        }
    }
}