<?php

namespace common\components\queue\vk;

use common\components\vk\VkApiHelper;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Батч-джоб для массовой отправки VK сообщений
 * Отправляет несколько сообщений за раз, соблюдая rate limits VK API
 */
class SendVkMessageBatchJob extends BaseObject implements JobInterface
{
    /**
     * @var array Массив сообщений для отправки
     * Формат: [['user_id' => int, 'message' => string, 'photo' => string|null], ...]
     */
    public $messages = [];
    
    /**
     * @var int Задержка между отправками в микросекундах (по умолчанию 350ms для соблюдения rate limit 3 req/sec)
     */
    public $delayBetweenMessages = 350000; // 0.35 секунды
    
    /**
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        if (empty($this->messages)) {
            Yii::warning("SendVkMessageBatchJob: Empty messages array", __METHOD__);
            return false;
        }

        $vkApi = new VkApiHelper();
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($this->messages as $index => $messageData) {
            try {
                $userId = $messageData['user_id'] ?? null;
                $message = $messageData['message'] ?? '';
                $photo = $messageData['photo'] ?? null;

                if (empty($userId)) {
                    Yii::warning("SendVkMessageBatchJob: Empty user_id in message at index {$index}", __METHOD__);
                    $errorCount++;
                    continue;
                }

                $result = $vkApi->sendMessage($userId, $message, $photo);

                // Проверяем успешность отправки
                if ($result === false || !empty($result['error'])) {
                    $errorMessage = isset($result['error']['error_msg']) ? $result['error']['error_msg'] : 'Unknown error';
                    $errorCode = isset($result['error']['error_code']) ? $result['error']['error_code'] : 'N/A';
                    
                    $errors[] = [
                        'user_id' => $userId,
                        'error_code' => $errorCode,
                        'error_message' => $errorMessage,
                    ];
                    
                    // Для некоторых ошибок (например, пользователь заблокировал сообщения) не нужно повторять попытку
                    // Для других ошибок можно добавить в очередь повторно
                    if ($errorCode == 901) { // Can't send messages to this user due to their privacy settings
                        Yii::info("SendVkMessageBatchJob: Can't send to user {$userId} due to privacy settings", __METHOD__);
                    } else {
                        Yii::warning("SendVkMessageBatchJob: Failed to send message to VK user {$userId}. Error code: {$errorCode}, Message: {$errorMessage}", __METHOD__);
                    }
                    
                    $errorCount++;
                } else {
                    $successCount++;
                }

                // Задержка между сообщениями для соблюдения rate limits VK API (3 запроса в секунду)
                // Пропускаем задержку для последнего сообщения
                if ($index < count($this->messages) - 1) {
                    usleep($this->delayBetweenMessages);
                }
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = [
                    'user_id' => $messageData['user_id'] ?? 'unknown',
                    'error_code' => 'EXCEPTION',
                    'error_message' => $e->getMessage(),
                ];
                Yii::error("SendVkMessageBatchJob: Exception sending message to user " . ($messageData['user_id'] ?? 'unknown') . ": " . $e->getMessage(), __METHOD__);
            }
        }

        // Логируем итоговую статистику
        if ($errorCount > 0) {
            Yii::warning("SendVkMessageBatchJob: Batch completed. Success: {$successCount}, Errors: {$errorCount}. Errors: " . json_encode($errors, JSON_UNESCAPED_UNICODE), __METHOD__);
        } else {
            Yii::info("SendVkMessageBatchJob: Batch completed successfully. Sent: {$successCount}", __METHOD__);
        }

        return $successCount > 0;
    }
}





