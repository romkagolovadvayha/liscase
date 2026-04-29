<?php

namespace common\components\telegram;

use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;

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
        [$url, $params] = $this->_getUrl($method, $params);
        $postParamsForLog = \is_array($params) ? $params : [];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, "PostManGoBot 1.0");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        TelegramCurlProxy::applyFromSettings($ch);

        if (!empty($params)) {
            $attachments = ['photo', 'audio', 'document', 'video'];
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
        $curlError = '';
        if ($answer === false) {
            $curlError = curl_error($ch);
            Yii::error('empty telegram query answer ' . $curlError);
        }

        $this->forwardApiResponseToTelegramChats(
            $method,
            $this->buildRequestSummaryForLog($url, $postParamsForLog),
            $answer !== false ? (string) $answer : '(curl failed) ' . $curlError
        );

        curl_close($ch);

        return json_decode($answer, true);
    }

    /**
     * Дублирует запрос и сырой ответ Bot API в alert-чат (tgbotAlert_*).
     */
    private function forwardApiResponseToTelegramChats(string $method, string $requestSummary, string $responseBody): void
    {
        if (!Yii::$app->has('telegramChats')) {
            return;
        }

        $prefix = '[TelegramSupport] ' . $method . "\n";
        $plain = "— request —\n" . $requestSummary . "\n\n— response —\n" . $responseBody;
        $encoded = Html::encode($plain);
        $maxContent = 4000 - mb_strlen($prefix, 'UTF-8');
        if ($maxContent < 200) {
            $maxContent = 200;
        }
        if (mb_strlen($encoded, 'UTF-8') > $maxContent) {
            $encoded = mb_substr($encoded, 0, $maxContent, 'UTF-8') . "\n… (truncated)";
        }

        try {
            Yii::$app->telegramChats->sendMessage('<pre>' . $prefix . $encoded . '</pre>');
        } catch (\Throwable $e) {
            Yii::warning('TelegramSupport telegramChats: ' . $e->getMessage(), __METHOD__);
        }
    }

    private function buildRequestSummaryForLog(string $url, array $postBodyParams): string
    {
        $lines = [$this->redactTelegramApiUrl($url)];
        if ($postBodyParams !== []) {
            try {
                $lines[] = Json::encode(
                    $postBodyParams,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                );
            } catch (\Throwable $e) {
                $lines[] = print_r($postBodyParams, true);
            }
        }

        return implode("\n", $lines);
    }

    private function redactTelegramApiUrl(string $url): string
    {
        $redacted = preg_replace('#(https://api\.telegram\.org/bot)[^/]+#i', '$1***', $url);

        return $redacted !== null ? $redacted : $url;
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
}