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

class SendPhotoJob extends BaseObject implements JobInterface
{
    public $telegram_chat_id;
    public $photo;
    public $message;
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
            // Логируем информацию о фото для диагностики
            $photoInfo = is_string($this->photo) ? $this->photo : gettype($this->photo);
            $photoLength = is_string($this->photo) ? strlen($this->photo) : 0;
            $isUrl = is_string($this->photo) && preg_match('#^https?://#i', $this->photo);
            $isFile = is_string($this->photo) && file_exists($this->photo) && is_file($this->photo);
            
            Yii::info("SendPhotoJob: Attempting to send photo to chat_id {$this->telegram_chat_id}. " .
                "Photo type: " . gettype($this->photo) . ", " .
                "Is URL: " . ($isUrl ? 'yes' : 'no') . ", " .
                "Is file: " . ($isFile ? 'yes' : 'no') . ", " .
                "Length: {$photoLength}, " .
                "Preview: " . (is_string($this->photo) ? substr($this->photo, 0, 100) : 'N/A'), 
                __METHOD__);
            
            $result = Yii::$app->personalBotTelegram->sendPhoto($this->telegram_chat_id, $this->photo, $this->message);
            
            // Проверяем успешность отправки
            if ($result === false || (isset($result['ok']) && !$result['ok'])) {
                $errorMessage = isset($result['description']) ? $result['description'] : 'Unknown error';
                $errorCode = isset($result['error_code']) ? $result['error_code'] : 'N/A';
                
                // Дополнительное логирование при ошибке
                Yii::error("SendPhotoJob: Error details - Photo: {$photoInfo}, Error: {$errorMessage} (code: {$errorCode})", __METHOD__);
                
                // Проверяем, заблокирован ли бот пользователем или деактивирован ли пользователь
                $isBlocked = stripos($errorMessage, 'bot was blocked by the user') !== false 
                    || stripos($errorMessage, 'bot was blocked') !== false
                    || stripos($errorMessage, 'chat not found') !== false
                    || stripos($errorMessage, 'user is deactivated') !== false;
                
                if ($isBlocked) {
                    // Устанавливаем флаг is_telegram_blocked для пользователя
                    $user = User::find()
                        ->where(['telegram_chat_id' => $this->telegram_chat_id])
                        ->one();
                    
                    if ($user) {
                        $user->is_telegram_blocked = 1;
                        $user->save(false);
                        Yii::info("SendPhotoJob: User {$user->id} (chat_id {$this->telegram_chat_id}) blocked the bot. Flag is_telegram_blocked set to 1.", __METHOD__);
                    } else {
                        Yii::warning("SendPhotoJob: User with chat_id {$this->telegram_chat_id} not found. Cannot set is_telegram_blocked flag.", __METHOD__);
                    }
                    
                    // Не повторяем попытки для заблокированных пользователей
                    return false;
                }
                
                // Если не превышен лимит попыток, отправляем в очередь повторно
                if ($this->attempt < self::MAX_ATTEMPTS) {
                    $this->attempt++;
                    $delay = $this->attempt * 5; // Увеличиваем задержку с каждой попыткой: 5, 10, 15 секунд
                    
                    Yii::warning("SendPhotoJob: Failed to send photo to chat_id {$this->telegram_chat_id} (attempt {$this->attempt}/" . self::MAX_ATTEMPTS . "). Retrying in {$delay}s. Error: {$errorMessage}", __METHOD__);
                    
                    // Отправляем в очередь повторно с задержкой
                    Yii::$app->queueTelegram->delay($delay)->push($this);
                    return false;
                }
                
                // Превышен лимит попыток - логируем ошибку
                Yii::error("SendPhotoJob: Failed to send photo to chat_id {$this->telegram_chat_id} after " . self::MAX_ATTEMPTS . " attempts. Error code: {$errorCode}, Message: {$errorMessage}", __METHOD__);
                Yii::$app->telegramChats->sendMessage("SendPhotoJob: Failed to send photo to chat_id {$this->telegram_chat_id} after " . self::MAX_ATTEMPTS . " attempts. Error: {$errorMessage}");
                return false;
            }
            
            // Успешная отправка
            if ($this->attempt > 1) {
                Yii::info("SendPhotoJob: Successfully sent photo to chat_id {$this->telegram_chat_id} on attempt {$this->attempt}", __METHOD__);
            }
            return true;
        } catch (\Exception $e) {
            // Если не превышен лимит попыток, отправляем в очередь повторно
            if ($this->attempt < self::MAX_ATTEMPTS) {
                $this->attempt++;
                $delay = $this->attempt * 5;
                
                Yii::warning("SendPhotoJob: Exception on attempt {$this->attempt}/" . self::MAX_ATTEMPTS . ". Retrying in {$delay}s. Error: " . $e->getMessage(), __METHOD__);
                
                Yii::$app->queueTelegram->delay($delay)->push($this);
                return false;
            }
            
            // Превышен лимит попыток
            Yii::error("SendPhotoJob: Exception after " . self::MAX_ATTEMPTS . " attempts - " . $e->getMessage() . "\n" . $e->getTraceAsString(), __METHOD__);
            Yii::$app->telegramChats->sendMessage("SendPhotoJob: Exception after " . self::MAX_ATTEMPTS . " attempts - " . $e->getMessage());
            return false;
        }
    }
}