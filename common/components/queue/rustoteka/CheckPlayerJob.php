<?php

namespace common\components\queue\rustoteka;

use common\components\telegram\foreignSystem\RustotekaBotSystem;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class CheckPlayerJob extends BaseObject implements JobInterface
{
    public $chatId;
    public $steamId;
    public $waitingMessageId; // ID сообщения ожидания, которое нужно удалить

    /**
     * Выполнение задачи проверки игрока в очереди
     * 
     * ВНИМАНИЕ: Все запросы к внешним сервисам и базам данных выполняются здесь, в очереди:
     * - Запрос к Steam API (Steam::getInfoUser)
     * - Запрос агрегированного профиля Rust Admin
     * - Запрос к GeoIP для определения страны по IP
     * - Запрос к локальной базе данных (BanList::find)
     * 
     * Это позволяет не блокировать основной поток обработки сообщений бота.
     * 
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        $botSystem = null;
        $bot = null;
        
        try {
            $botSystem = new RustotekaBotSystem();
            $bot = $botSystem->getTelegramBot();
            
            Yii::info("CheckPlayerJob: Starting check for steamId {$this->steamId}, chatId {$this->chatId} (executing in queue)", __METHOD__);
            
            // Получаем результат проверки (все запросы к сервисам и БД выполняются внутри этого метода в очереди)
            $result = $botSystem->getCheck($this->steamId);
            
            Yii::info("CheckPlayerJob: Got result, is_array: " . (is_array($result) ? 'yes' : 'no') . ", has message: " . (is_array($result) && !empty($result['message']) ? 'yes' : 'no'), __METHOD__);
            
            if (is_array($result) && !empty($result['message'])) {
                $messageLength = mb_strlen($result['message'], 'UTF-8');
                Yii::info("CheckPlayerJob: Message length: {$messageLength} characters", __METHOD__);
                
                // Telegram имеет лимит 4096 символов на сообщение
                if ($messageLength > 4096) {
                    Yii::warning("CheckPlayerJob: Message is too long ({$messageLength} chars), truncating to 4096", __METHOD__);
                    $result['message'] = mb_substr($result['message'], 0, 4090, 'UTF-8') . "\n\n...";
                }
            }
            
            // Удаляем сообщение ожидания
            if (!empty($this->waitingMessageId)) {
                try {
                    $deleteResult = $bot->deleteMessage($this->chatId, $this->waitingMessageId);
                    if ($deleteResult && isset($deleteResult['ok']) && !$deleteResult['ok']) {
                        Yii::warning("CheckPlayerJob: Failed to delete waiting message {$this->waitingMessageId}: " . ($deleteResult['description'] ?? 'Unknown error'), __METHOD__);
                    }
                } catch (\Exception $e) {
                    // Игнорируем ошибку удаления, если сообщение уже удалено или не найдено
                    Yii::warning("CheckPlayerJob: Exception deleting waiting message {$this->waitingMessageId}: " . $e->getMessage(), __METHOD__);
                }
            }
            
            // Отправляем результат
            if (is_array($result) && !empty($result['message'])) {
                $buttons = $result['buttons'] ?? [];
                Yii::info("CheckPlayerJob: Sending message with buttons, buttons count: " . count($buttons), __METHOD__);
                
                // Сначала пытаемся отправить с кнопками
                $sendResult = $bot->sendMessage($this->chatId, $result['message'], $buttons);
                
                if ($sendResult === false || (isset($sendResult['ok']) && !$sendResult['ok'])) {
                    $errorMsg = isset($sendResult['description']) ? $sendResult['description'] : 'Unknown error';
                    Yii::error("CheckPlayerJob: Failed to send result message with buttons: {$errorMsg}", __METHOD__);
                    Yii::error("CheckPlayerJob: Buttons structure: " . json_encode($buttons, JSON_UNESCAPED_UNICODE), __METHOD__);
                    
                    // Пытаемся отправить без кнопок
                    Yii::info("CheckPlayerJob: Trying to send message without buttons", __METHOD__);
                    $sendResult = $bot->sendMessage($this->chatId, $result['message'], []);
                    
                    if ($sendResult === false || (isset($sendResult['ok']) && !$sendResult['ok'])) {
                        $errorMsg2 = isset($sendResult['description']) ? $sendResult['description'] : 'Unknown error';
                        Yii::error("CheckPlayerJob: Failed to send result message without buttons: {$errorMsg2}", __METHOD__);
                    } else {
                        Yii::info("CheckPlayerJob: Successfully sent result message without buttons", __METHOD__);
                    }
                } else {
                    Yii::info("CheckPlayerJob: Successfully sent result message with buttons", __METHOD__);
                }
            } elseif (is_string($result) && !empty($result)) {
                Yii::info("CheckPlayerJob: Sending string result", __METHOD__);
                $sendResult = $bot->sendMessage($this->chatId, $result);
                
                if ($sendResult === false || (isset($sendResult['ok']) && !$sendResult['ok'])) {
                    $errorMsg = isset($sendResult['description']) ? $sendResult['description'] : 'Unknown error';
                    Yii::error("CheckPlayerJob: Failed to send string result: {$errorMsg}", __METHOD__);
                } else {
                    Yii::info("CheckPlayerJob: Successfully sent string result", __METHOD__);
                }
            } else {
                Yii::error("CheckPlayerJob: Invalid result format. Result: " . print_r($result, true), __METHOD__);
                
                // Отправляем сообщение об ошибке
                if ($bot) {
                    $bot->sendMessage($this->chatId, "❌ Произошла ошибка при обработке результата проверки.");
                }
            }
            
        } catch (\Exception $e) {
            Yii::error("CheckPlayerJob: Exception checking player {$this->steamId} for chat {$this->chatId}: " . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            
            // Удаляем сообщение ожидания даже при ошибке
            if (!empty($this->waitingMessageId)) {
                try {
                    if (!$bot && !$botSystem) {
                        $botSystem = new RustotekaBotSystem();
                        $bot = $botSystem->getTelegramBot();
                    }
                    if ($bot) {
                        $bot->deleteMessage($this->chatId, $this->waitingMessageId);
                    }
                } catch (\Exception $deleteEx) {
                    // Игнорируем ошибку удаления
                    Yii::warning("CheckPlayerJob: Failed to delete waiting message on error: " . $deleteEx->getMessage(), __METHOD__);
                }
            }
            
            // Отправляем сообщение об ошибке
            try {
                if (!$bot && !$botSystem) {
                    $botSystem = new RustotekaBotSystem();
                    $bot = $botSystem->getTelegramBot();
                }
                if ($bot) {
                    $errorResult = $bot->sendMessage($this->chatId, "❌ Произошла ошибка при проверке игрока. Попробуйте позже.");
                    if ($errorResult === false || (isset($errorResult['ok']) && !$errorResult['ok'])) {
                        Yii::error("CheckPlayerJob: Failed to send error message: " . ($errorResult['description'] ?? 'Unknown error'), __METHOD__);
                    }
                }
            } catch (\Exception $sendEx) {
                Yii::error("CheckPlayerJob: Exception sending error message: " . $sendEx->getMessage(), __METHOD__);
            }
        }
    }
}

