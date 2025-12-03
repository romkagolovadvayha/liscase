<?php

namespace common\components\queue\telegram;

use common\components\oauth\Steam;
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

class SendMessageJob extends BaseObject implements JobInterface
{
    public $telegram_chat_id;
    public $message;
    public $buttons;
    public $attempt = 1; // Номер попытки отправки
    public const MAX_ATTEMPTS = 3; // Максимальное количество попыток

    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            $result = Yii::$app->personalBotTelegram->sendMessage($this->telegram_chat_id, $this->message, $this->buttons);
            
            // Проверяем успешность отправки
            if ($result === false || (isset($result['ok']) && !$result['ok'])) {
                $errorMessage = isset($result['description']) ? $result['description'] : 'Unknown error';
                $errorCode = isset($result['error_code']) ? $result['error_code'] : 'N/A';
                
                // Если не превышен лимит попыток, отправляем в очередь повторно
                if ($this->attempt < self::MAX_ATTEMPTS) {
                    $this->attempt++;
                    $delay = $this->attempt * 60; // Увеличиваем задержку с каждой попыткой: 5, 10, 15 секунд
                    
                    Yii::warning("SendMessageJob: Failed to send message to chat_id {$this->telegram_chat_id} (attempt {$this->attempt}/" . self::MAX_ATTEMPTS . "). Retrying in {$delay}s. Error: {$errorMessage}", __METHOD__);
                    
                    sleep($delay);
                    // Отправляем в очередь повторно с задержкой
                    Yii::$app->queueTelegram->delay($delay)->push($this);
                    return false;
                }
                
                // Превышен лимит попыток - логируем ошибку
                Yii::error("SendMessageJob: Failed to send message to chat_id {$this->telegram_chat_id} after " . self::MAX_ATTEMPTS . " attempts. Error code: {$errorCode}, Message: {$errorMessage}", __METHOD__);
                Yii::$app->telegramChats->sendMessage("SendMessageJob: Failed to send message to chat_id {$this->telegram_chat_id} after " . self::MAX_ATTEMPTS . " attempts. Error: {$errorMessage}");
                return false;
            }
            
            // Успешная отправка
            if ($this->attempt > 1) {
                Yii::info("SendMessageJob: Successfully sent message to chat_id {$this->telegram_chat_id} on attempt {$this->attempt}", __METHOD__);
            }
            return true;
        } catch (\Exception $e) {
            // Если не превышен лимит попыток, отправляем в очередь повторно
            if ($this->attempt < self::MAX_ATTEMPTS) {
                $this->attempt++;
                $delay = $this->attempt * 5;
                
                Yii::warning("SendMessageJob: Exception on attempt {$this->attempt}/" . self::MAX_ATTEMPTS . ". Retrying in {$delay}s. Error: " . $e->getMessage(), __METHOD__);
                
                Yii::$app->queueTelegram->delay($delay)->push($this);
                return false;
            }
            
            // Превышен лимит попыток
            Yii::error("SendMessageJob: Exception after " . self::MAX_ATTEMPTS . " attempts - " . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            Yii::$app->telegramChats->sendMessage("SendMessageJob: Exception after " . self::MAX_ATTEMPTS . " attempts - " . $e->getMessage());
            return false;
        }
    }
}