<?php

namespace common\components\telegram;

use Yii;
use yii\helpers\ArrayHelper;

class TelegramApiHelper extends \yii\base\Component
{
    public string $token;

    /**
     * @return mixed
     */
    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMe()
    {
        return $this->_sendRequest('getMe');
    }

    /**
     * @param string $method
     * @param array  $params
     *
     * @return mixed
     */
    private function _sendRequest($method, $params = [])
    {
        list($url, $params) = $this->_getUrl($method, $params);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, "PostManGoBot 1.0");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        if (!empty($params)) {

            $attachments = ['photo', 'sticker', 'audio', 'document', 'video'];
            $hasFile = false;

            foreach ($attachments as $attachment) {
                if (isset($params[$attachment])) {
                    $value = $params[$attachment];
                    
                    // Если это удалённый URL или Telegram file_id — отправляем как текст
                    if (is_string($value) && preg_match('#^https?://#i', $value)) {
                        continue; // URL отправляется как строка, не как файл
                    }
                    if (is_array($value) && isset($value['file_id'])) {
                        continue; // file_id отправляется как есть
                    }
                    
                    $file = $this->curlFile($value);
                    if ($file instanceof \CURLFile || (is_string($file) && isset($file[0]) && $file[0] === '@')) {
                        $params[$attachment] = $file;
                        $hasFile = true;
                        break;
                    }
                }
            }

            curl_setopt($ch, CURLOPT_POST, true);
            
            // Если есть файл, отправляем как multipart/form-data
            if ($hasFile) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            }
        }

        $answer = curl_exec($ch);
        if ($answer === false) {
            Yii::$app->telegramChats->sendMessage('empty telegram query answer ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($answer, true);
    }

    /**
     * @param string $method
     * @param array  $params
     *
     * @return array
     */
    private function _getUrl($method, $params = [])
    {
        $chatId      = ArrayHelper::getValue($params, 'chat_id');
        $messageText = ArrayHelper::getValue($params, 'text');

        $getParams = [];
        if (!empty($chatId)) {
            $getParams['chat_id'] = $chatId;
            unset($params['chat_id']);
        }

        if (!empty($messageText)) {
            $getParams['text'] = $messageText;
            unset($params['text']);
        }
        if (empty($this->token)) {
            $this->token = Yii::$app->settings->get('tgbot_botToken');
        }
        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $method .
               (!empty($getParams) ? '?' . http_build_query($getParams) : '');

        return [$url, $params];
    }

    /**
     * @param string $path
     *
     * @return \CURLFile|mixed|string
     */
    private function curlFile($path)
    {
        if (is_array($path)) {
            if (isset($path['file_id'])) {
                return $path['file_id'];
            }
            if (isset($path['path'])) {
                $path = $path['path'];
            }
        }

        // Если это URL, возвращаем как есть (Telegram сам скачает)
        if (is_string($path) && preg_match('#^https?://#i', $path)) {
            return $path;
        }

        // Проверяем, существует ли файл
        $realPath = is_string($path) ? realpath($path) : false;
        if ($realPath === false || !is_file($realPath)) {
            Yii::error('TelegramApiHelper: file not found for upload: ' . print_r($path, true), __METHOD__);
            return $path; // Возвращаем исходный путь, возможно это file_id или URL
        }

        // Определяем MIME-тип для фото
        $mimeType = null;
        if (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($realPath);
        } elseif (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($realPath);
        }

        // Если MIME-тип не определен, пытаемся определить по расширению
        if (empty($mimeType)) {
            $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
            ];
            $mimeType = isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'image/jpeg';
        }

        if (class_exists('CURLFile')) {
            return new \CURLFile($realPath, $mimeType, basename($realPath));
        }

        return '@' . $realPath;
    }

    /**
     * @param int    $chatId
     * @param string $messageText
     * @param array  $inlineKeyboard
     *
     * @return mixed
     */
    public function sendMessage($chatId, $messageText, $inlineKeyboard = [])
    {
        if (empty($messageText)) {
            return false;
        }

        $params = [
            'chat_id'      => $chatId,
            'text'         => $messageText,
            'parse_mode'   => 'Html',
        ];

        if (!empty($inlineKeyboard)) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => [$inlineKeyboard]
            ]);
        }

        return $this->_sendRequest('sendMessage', $params);
    }
    /**
     * @param int    $chatId
     * @param string $messageText
     * @param array  $inlineKeyboard
     *
     * @return mixed
     */
    public function editMessageReplyMarkup($chatId, $messageId, $inlineKeyboard = [])
    {
        $params = [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'parse_mode'   => 'Html',
        ];

        if (!empty($inlineKeyboard)) {
            $params['reply_markup'] = json_encode([
                                                      'inline_keyboard' => [$inlineKeyboard]
                                                  ]);
        }
        $this->_sendRequest("editMessageReplyMarkup", $params);
    }

    /**
     * @param int   $chatId
     * @param array $medias
     *
     * @return mixed
     */
    public function sendMediaGroup($chatId, $medias)
    {
        $answer = $this->_sendRequest('sendMediaGroup', [
            'chat_id' => $chatId,
            'media'   => json_encode($medias),
        ]);

        return ArrayHelper::getValue($answer, 'result', []);
    }

    /**
     * @param int    $chatId
     * @param int    $messageId
     * @param string $messageText
     *
     * @return mixed
     */
    public function editMessageText($chatId, $messageId, $messageText)
    {
        if (empty($messageText)) {
            return false;
        }

        return $this->_sendRequest('editMessageText', [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $messageText,
            'parse_mode' => 'Html',
        ]);
    }

    /**
     * @param int    $chatId
     * @param int    $messageId
     * @param string $messageText
     *
     * @return mixed
     */
    public function editMessageCaption($chatId, $messageId, $messageText)
    {
        if (empty($messageText)) {
            return false;
        }

        return $this->_sendRequest('editMessageCaption', [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'caption'       => $messageText,
            'parse_mode' => 'Html',
        ]);
    }

    /**
     * @param int    $chatId
     * @param string $messageId
     *
     * @return mixed
     */
    public function deleteMessage($chatId, $messageId)
    {
        return $this->_sendRequest('deleteMessage', [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * @param int    $chatId
     * @param string $photo
     * @param string $caption
     *
     * @return mixed
     */
    public function sendPhoto($chatId, $photo, $caption = '')
    {
        return $this->_sendRequest('sendPhoto', [
            'chat_id' => $chatId,
            'photo'   => $photo,
            'caption' => $caption,
            'parse_mode'   => 'Html',
        ]);
    }

    /**
     * @param string $audio Path to audio file
     * @param string $caption
     * @param array  $inlineKeyboard
     * @param int|null $chatId Optional chat ID (uses default from settings if not provided)
     *
     * @return mixed
     */
    public function sendAudio($audio, $caption = '', $inlineKeyboard = [], $chatId = null)
    {
        // Get chat ID from settings if not provided (for telegramSupport)
        if ($chatId === null) {
            $chatId = Yii::$app->settings->get('tgbotSupportAlert_chatId');
        }
        
        $params = [
            'chat_id' => $chatId,
            'audio'   => $audio,
            'caption' => $caption,
            'parse_mode' => 'Html',
        ];
        
        if (!empty($inlineKeyboard)) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => [$inlineKeyboard]
            ]);
        }
        
        return $this->_sendRequest('sendAudio', $params);
    }

    /**
     * @param int    $chatId
     * @param string $action
     *
     * @return mixed
     */
    public function sendChatAction($chatId, $action)
    {
        return $this->_sendRequest('sendChatAction', [
            'chat_id' => $chatId,
            'action'  => $action,
        ]);
    }

    /**
     * @return mixed
     */
    public function getUpdates()
    {
        return $this->_sendRequest('getUpdates');
    }

    /**
     * @param string $url
     *
     * @return mixed
     */
    public function setWebHook($url)
    {
        return $this->_sendRequest('setWebhook', [
            'url' => $url,
        ]);
    }

    /**
     * @param int $chatId
     *
     * @return mixed
     */
    public function getChat($chatId)
    {
        return $this->_sendRequest('getChat', [
            'chat_id' => $chatId,
        ]);
    }

    /**
     * @param int    $chatId
     * @param string $title
     *
     * @return mixed
     */
    public function setChatTitle($chatId, $title)
    {
        return $this->_sendRequest('setChatTitle', [
            'chat_id' => $chatId,
            'title'   => $title,
        ]);
    }

    /**
     * @param int    $chatId
     * @param string $title
     *
     * @return mixed
     */
    public function setChatDescription($chatId, $title)
    {
        return $this->_sendRequest('setChatDescription', [
            'chat_id'     => $chatId,
            'description' => $title,
        ]);
    }

    /**
     * @param $chatId
     * @param $userId
     *
     * @return mixed
     */
    public function getChatMember($chatId, $userId)
    {
        return $this->_sendRequest('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    /**
     * @param $chatId
     *
     * @return mixed
     */
    public function getChatAdministrators($chatId)
    {
        return $this->_sendRequest('getChatAdministrators', [
            'chat_id' => $chatId,
        ]);
    }

    /**
     * @param int $chatId
     *
     * @return mixed
     */
    public function getChatMemberCount($chatId)
    {
        return $this->_sendRequest('getChatMemberCount', [
            'chat_id' => $chatId,
        ]);
    }
}