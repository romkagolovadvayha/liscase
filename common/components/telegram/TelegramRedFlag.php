<?php

namespace common\components\telegram;

use Yii;
use yii\helpers\ArrayHelper;

class TelegramRedFlag
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
        if (!TelegramNotificationSettings::isEnabled('tgbotRedFlag')) {
            return [];
        }

        [$url, $params] = $this->_getUrl($method, $params);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, "PostManGoBot 1.0");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        TelegramCurlProxy::applyFromSettings($ch);

        if (!empty($params)) {

            $attachments = ['photo', 'sticker', 'audio', 'document', 'video'];

            foreach ($attachments as $attachment) {
                if (isset($params[$attachment])) {
                    $params[$attachment] = $this->curlFile($params[$attachment]);
                    break;
                }
            }

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $answer = curl_exec($ch);

        if ($answer === false) {
            Yii::error('empty telegram query answer ' . curl_error($ch), __METHOD__);
        }


        return json_decode($answer, true);
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
            return $path;
        }

        $realPath = is_string($path) ? realpath($path) : false;
        if ($realPath === false || !is_file($realPath)) {
            Yii::error('TelegramRedFlag: file not found for upload: ' . print_r($path, true), __METHOD__);
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

        $token = Yii::$app->settings->get('tgbotRedFlag_token');
        $url = 'https://api.telegram.org/bot' . $token . '/' . $method .
            (!empty($getParams) ? '?' . http_build_query($getParams) : '');

        return [$url, $params];
    }

    /**
     * @param $messageText
     * @param array $inlineKeyboard
     *
     * @return array
     * @throws \Exception
     */
    public function sendMessage($messageText, $inlineKeyboard = []): array
    {
        $chatId = Yii::$app->settings->get('tgbotRedFlag_chatId');
        $params = [
            'chat_id'      => $chatId,
            'text'         => $messageText,
            'parse_mode'   => 'Html',
            'link_preview_options'   => [
                'is_disabled' => true
            ],
        ];
        if (!empty($inlineKeyboard)) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $inlineKeyboard
            ]);
        }
        $result = $this->sendHttpRequest("sendMessage", $params);

        return $result;
    }
}

