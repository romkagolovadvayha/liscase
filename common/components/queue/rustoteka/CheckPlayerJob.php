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
     * @param \yii\queue\Queue $queue
     *
     * @return mixed|void
     * @throws \Exception
     */
    public function execute($queue)
    {
        try {
            $botSystem = new RustotekaBotSystem();
            $bot = $botSystem->getTelegramBot();
            
            // Получаем результат проверки
            $result = $botSystem->getCheck($this->steamId);
            
            // Удаляем сообщение ожидания
            if (!empty($this->waitingMessageId)) {
                try {
                    $bot->deleteMessage($this->chatId, $this->waitingMessageId);
                } catch (\Exception $e) {
                    // Игнорируем ошибку удаления, если сообщение уже удалено или не найдено
                    Yii::warning("CheckPlayerJob: Failed to delete waiting message {$this->waitingMessageId}: " . $e->getMessage(), __METHOD__);
                }
            }
            
            // Отправляем результат
            if (is_array($result) && !empty($result['message'])) {
                $bot->sendMessage($this->chatId, $result['message'], $result['buttons'] ?? []);
            } elseif (is_string($result)) {
                $bot->sendMessage($this->chatId, $result);
            }
            
        } catch (\Exception $e) {
            Yii::error("CheckPlayerJob: Error checking player {$this->steamId} for chat {$this->chatId}: " . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            
            // Удаляем сообщение ожидания даже при ошибке
            if (!empty($this->waitingMessageId)) {
                try {
                    $botSystem = new RustotekaBotSystem();
                    $bot = $botSystem->getTelegramBot();
                    $bot->deleteMessage($this->chatId, $this->waitingMessageId);
                } catch (\Exception $deleteEx) {
                    // Игнорируем ошибку удаления
                }
            }
            
            // Отправляем сообщение об ошибке
            try {
                $botSystem = new RustotekaBotSystem();
                $bot = $botSystem->getTelegramBot();
                $bot->sendMessage($this->chatId, "❌ Произошла ошибка при проверке игрока. Попробуйте позже.");
            } catch (\Exception $sendEx) {
                Yii::error("CheckPlayerJob: Failed to send error message: " . $sendEx->getMessage(), __METHOD__);
            }
        }
    }
}

