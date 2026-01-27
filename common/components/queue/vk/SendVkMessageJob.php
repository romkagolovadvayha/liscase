<?php

namespace common\components\queue\vk;

use common\components\vk\VkApiHelper;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class SendVkMessageJob extends BaseObject implements JobInterface
{
    public $user_id;
    public $message;
    public $photo;
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
            $vkApi = new VkApiHelper();
            $result = $vkApi->sendMessage($this->user_id, $this->message, $this->photo);
            
            // Проверяем успешность отправки
            if ($result === false || !empty($result['error'])) {
                $errorMessage = isset($result['error']['error_msg']) ? $result['error']['error_msg'] : 'Unknown error';
                $errorCode = isset($result['error']['error_code']) ? $result['error']['error_code'] : 'N/A';
                
                // Если не превышен лимит попыток, отправляем в очередь повторно
                if ($this->attempt < self::MAX_ATTEMPTS) {
                    $this->attempt++;
                    $delay = $this->attempt * 60; // Увеличиваем задержку с каждой попыткой: 5, 10, 15 секунд
                    
                    Yii::warning("SendVkMessageJob: Failed to send message to VK user {$this->user_id} (attempt {$this->attempt}/" . self::MAX_ATTEMPTS . "). Retrying in {$delay}s. Error code: {$errorCode}, Message: {$errorMessage}", __METHOD__);
                    
                    sleep($delay);
                    // Отправляем в очередь повторно с задержкой
                    Yii::$app->queueVk->delay($delay)->push($this);
                    return false;
                }
                
                // Превышен лимит попыток - логируем ошибку
                Yii::error("SendVkMessageJob: Failed to send message to VK user {$this->user_id} after " . self::MAX_ATTEMPTS . " attempts. Error code: {$errorCode}, Message: {$errorMessage}", __METHOD__);
                return false;
            }
            
            // Успешная отправка
            if ($this->attempt > 1) {
                Yii::info("SendVkMessageJob: Successfully sent message to VK user {$this->user_id} on attempt {$this->attempt}", __METHOD__);
            }
            return true;
        } catch (\Exception $e) {
            // Если не превышен лимит попыток, отправляем в очередь повторно
            if ($this->attempt < self::MAX_ATTEMPTS) {
                $this->attempt++;
                $delay = $this->attempt * 5;
                
                Yii::warning("SendVkMessageJob: Exception on attempt {$this->attempt}/" . self::MAX_ATTEMPTS . ". Retrying in {$delay}s. Error: " . $e->getMessage(), __METHOD__);
                
                Yii::$app->queueVk->delay($delay)->push($this);
                return false;
            }
            
            // Превышен лимит попыток
            Yii::error("SendVkMessageJob: Exception after " . self::MAX_ATTEMPTS . " attempts - " . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            return false;
        }
    }
}

