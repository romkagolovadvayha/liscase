<?php

namespace common\components\telegram;

use Yii;
use yii\helpers\ArrayHelper;

class TelegramPayments
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
        if (!TelegramNotificationSettings::isEnabled('tgbotPayments')) {
            return [];
        }

        [$url, $params] = $this->_getUrl($method, $params);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
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
            curl_close($ch);
            return [];
        }

        curl_close($ch);
        $decoded = json_decode($answer, true);
        if (!is_array($decoded)) {
            Yii::error('Telegram Payments returned an invalid JSON response', 'payment');
            return [];
        }

        if (empty($decoded['ok'])) {
            Yii::error(
                'Telegram Payments rejected a message: ' . ($decoded['description'] ?? 'unknown error'),
                'payment'
            );
        }

        return $decoded;
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

        $token = Yii::$app->settings->get('tgbotPayments_token');
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
    public function sendMessage($messageText): array
    {
        $chatId = Yii::$app->settings->get('tgbotPayments_chatId');
        $params = [
            'chat_id'      => $chatId,
            'text'         => $messageText,
            'parse_mode'   => 'Html',
        ];
        return $this->sendHttpRequest("sendMessage", $params);
    }
}
