<?php

namespace common\components\telegram;

use Yii;
use yii\helpers\ArrayHelper;

class TelegramSupport
{
    /**
     * @param       $method
     * @param array $params
     *
     * @return array
     * @throws \Exception
     */
    public function sendHttpRequest($method, $params = null)
    {
        if (!TelegramNotificationSettings::isEnabled('tgbotSupportAlert')) {
            return [];
        }

        [$url, $params] = $this->_getUrl($method, $params);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, "PostManGoBot 1.0");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        TelegramCurlProxy::applyFromSettings($ch);

        if (!empty($params)) {
            $attachments = ['photo', 'audio', 'document', 'video', 'sticker'];
            $hasFile = false;

            foreach ($attachments as $attachment) {
                if (!isset($params[$attachment])) {
                    continue;
                }

                $value = $params[$attachment];

                // Если это удалённый URL или Telegram file_id — отправляем как текст
                if (is_string($value) && preg_match('#^https?://#i', $value)) {
                    continue;
                }
                if (is_array($value) && isset($value['file_id'])) {
                    continue;
                }

                $file = $this->curlFile($value);
                if ($file instanceof \CURLFile || (is_string($file) && isset($file[0]) && $file[0] === '@')) {
                    $params[$attachment] = $file;
                    $hasFile = true;
                    break;
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
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($answer === false) {
            Yii::error('TelegramSupport request failed: ' . $curlError, __METHOD__);

            return [];
        }

        $decoded = json_decode((string)$answer, true);
        if (!is_array($decoded)) {
            Yii::error(
                'TelegramSupport returned an invalid JSON response (HTTP ' . $httpCode . '): '
                . json_last_error_msg(),
                __METHOD__
            );

            return [];
        }

        if ($httpCode < 200 || $httpCode >= 300 || empty($decoded['ok'])) {
            Yii::error(
                'TelegramSupport rejected a request (HTTP ' . $httpCode . '): '
                . (string)($decoded['description'] ?? 'unknown error'),
                __METHOD__
            );
        }

        return $decoded;
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

        if (is_string($path) && preg_match('#^https?://#i', $path)) {
            // Удалённый URL — телеграм принимает строки
            return $path;
        }

        $realPath = is_string($path) ? realpath($path) : false;
        if ($realPath === false || !is_file($realPath)) {
            Yii::error('TelegramSupport: file not found for upload: ' . print_r($path, true));
            return $path;
        }

        if (class_exists('CURLFile')) {
            return new \CURLFile($realPath);
        }

        return '@' . $realPath;
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

        $token = Yii::$app->settings->get('tgbotSupportAlert_token');
        $url = 'https://api.telegram.org/bot' . $token . '/' . $method .
            (!empty($getParams) ? '?' . http_build_query($getParams) : '');

        return [$url, $params];
    }

    /**
     * @param $hashNames
     *
     * @return array
     * @throws \Exception
     */
    public function sendMessage($messageText, $inlineKeyboard = [], $photoUrl = null): array
    {
        $chatId = Yii::$app->settings->get('tgbotSupportAlert_chatId');

        if ($photoUrl) {
            // Отправляем фото
            $params = [
                'chat_id' => $chatId,
                'photo'   => $photoUrl,
                'caption' => $messageText,
                'parse_mode' => 'Html',
            ];
            if (!empty($inlineKeyboard)) {
                $params['reply_markup'] = json_encode([
                                                          'inline_keyboard' => [$inlineKeyboard]
                                                      ]);
            }
            return $this->sendHttpRequest("sendPhoto", $params);

        } else {
            // Обычное сообщение
            $params = [
                'chat_id' => $chatId,
                'text' => $messageText,
                'parse_mode' => 'Html',
                'link_preview_options' => [
                    'is_disabled' => true
                ],
            ];
            if (!empty($inlineKeyboard)) {
                $params['reply_markup'] = json_encode([
                                                          'inline_keyboard' => [$inlineKeyboard]
                                                      ]);
            }
            return $this->sendHttpRequest("sendMessage", $params);
        }
    }

    /**
     * @param string $audio Path to audio file
     * @param string $caption
     * @param array  $inlineKeyboard
     *
     * @return array
     */
    public function sendAudio($audio, $caption = '', $inlineKeyboard = [])
    {
        $chatId = Yii::$app->settings->get('tgbotSupportAlert_chatId');

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

        return $this->sendHttpRequest("sendAudio", $params);
    }

    /**
     * Sends a custom support sticker with an optional reply button.
     */
    public function sendSticker(string $stickerUrl, array $inlineKeyboard = []): array
    {
        $params = [
            'chat_id' => Yii::$app->settings->get('tgbotSupportAlert_chatId'),
            'sticker' => $stickerUrl,
        ];
        if ($inlineKeyboard !== []) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => [$inlineKeyboard],
            ]);
        }

        return $this->sendHttpRequest('sendSticker', $params);
    }
}
