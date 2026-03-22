<?php

namespace common\components\telegram;

use Yii;
use yii\helpers\ArrayHelper;

class TelegramReports
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
            Yii::error('empty telegram query answer ' . curl_error($ch));
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

        $token = Yii::$app->settings->get('tgbotReport_botToken');
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
    public function sendMessage($messageText, $inlineKeyboard = []): array
    {
        $chatId = Yii::$app->settings->get('tgbotReport_chatId');
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
                'inline_keyboard' => [$inlineKeyboard]
            ]);
        }
        return $this->sendHttpRequest("sendMessage", $params);
    }
}