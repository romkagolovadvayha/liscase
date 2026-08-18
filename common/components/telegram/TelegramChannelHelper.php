<?php

namespace common\components\telegram;

use Yii;
use yii\helpers\ArrayHelper;

class TelegramChannelHelper extends \yii\base\Component
{
    public string $accessToken;
    public string $apiBaseUrl = 'https://api.telegram.org/bot';

    /**
     * Установка access token
     * @param string $token
     * @return $this
     */
    public function setAccessToken($token)
    {
        $this->accessToken = $token;
        return $this;
    }

    /**
     * Публикация поста в Telegram канал
     * @param string|int $channelId ID канала или @username
     * @param string $message Текст сообщения
     * @param string|array|null $photoUrl URL изображения или массив URL изображений (опционально)
     * @return array|false
     */
    public function postToChannel($channelId, $message, $photoUrl = null)
    {
        // Нормализуем ID канала: если это не @username, добавляем @ если нужно
        // Для публичных каналов используется формат @channel_username
        // Для приватных каналов используется числовой ID (например, -1001234567890)
        if (strpos($channelId, '@') !== 0 && !is_numeric($channelId) && !preg_match('/^-\d+$/', $channelId)) {
            $channelId = '@' . ltrim($channelId, '@');
        }

        // Если есть фото, отправляем их
        if (!empty($photoUrl)) {
            $photoUrls = is_array($photoUrl) ? $photoUrl : [$photoUrl];
            
            // Отправляем первое фото с текстом
            $result = $this->sendPhoto($channelId, $photoUrls[0], $message);
            
            return $result;
        } else {
            // Отправляем только текст
            return $this->sendMessage($channelId, $message);
        }
    }

    /**
     * Отправка сообщения в канал
     * @param string|int $channelId ID канала или @username
     * @param string $message Текст сообщения
     * @return array|false
     */
    public function sendMessage($channelId, $message)
    {
        $params = [
            'chat_id' => $channelId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ];

        return $this->_sendRequest('sendMessage', $params);
    }

    /**
     * Отправка фото в канал
     * @param string|int $channelId ID канала или @username
     * @param string $photoUrl URL изображения
     * @param string $caption Подпись к фото (опционально)
     * @return array|false
     */
    public function sendPhoto($channelId, $photoUrl, $caption = '')
    {
        $params = [
            'chat_id' => $channelId,
            'photo' => $photoUrl,
            'parse_mode' => 'HTML',
        ];

        if (!empty($caption)) {
            $params['caption'] = $caption;
        }

        return $this->_sendRequest('sendPhoto', $params);
    }

    /**
     * Отправка запроса к Telegram API
     * @param string $method Метод API
     * @param array $params Параметры запроса
     * @return array|false
     */
    private function _sendRequest($method, $params = [])
    {
        if (empty($this->accessToken)) {
            Yii::error("Telegram: Access token is not set", __METHOD__);
            return false;
        }

        $url = $this->apiBaseUrl . $this->accessToken . '/' . $method;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        TelegramCurlProxy::applyFromSettings($ch);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if (empty($response)) {
            Yii::error("Telegram: Empty response, HTTP code: {$httpCode}, Error: {$curlError}", __METHOD__);
            return false;
        }

        $result = json_decode($response, true);
        
        if (empty($result) || !isset($result['ok'])) {
            Yii::error("Telegram: Invalid response, HTTP code: {$httpCode}, Response: {$response}", __METHOD__);
            return false;
        }

        if (!$result['ok']) {
            $errorDescription = $result['description'] ?? 'Unknown error';
            $errorCode = $result['error_code'] ?? 'N/A';
            Yii::error("Telegram API error (HTTP {$httpCode}): [{$errorCode}] {$errorDescription}, Response: {$response}", __METHOD__);
            return $result;
        }

        return $result;
    }
}

