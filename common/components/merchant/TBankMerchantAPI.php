<?php

namespace common\components\merchant;

use common\components\payments\PaymentCallbackHandler;
use Yii;

/**
 * Class TBankMerchantAPI
 *
 * @property integer     orderId
 * @property integer     Count
 * @property bool|string error
 * @property bool|string response
 * @property bool|string customerKey
 * @property bool|string status
 * @property bool|string paymentUrl
 * @property bool|string paymentId
 */
class TBankMerchantAPI
{
    private $_api_url;
    private $_terminalKey;
    private $_secretKey;

    /**
     * Constructor
     *
     * @param string $terminalKey Your Terminal name
     * @param string $secretKey   Secret key for terminal
     * @param string $api_url     Url for API
     */
    public function __construct($terminalKey, $secretKey)
    {
        $this->_api_url = 'https://securepay.tinkoff.ru/v2/';
        $this->_terminalKey = $terminalKey;
        $this->_secretKey = $secretKey;
    }

    public function sendHttpRequest($method, $serviceUrl, $params = [])
    {
        $params['Token'] = $this->_genToken($params);
        $url = $this->_api_url . $serviceUrl;
        $curl = Yii::$app->curl;

        if ($method == 'POST') {
            $curl->setHeader('Content-Type', 'application/json');
            $response = $curl->setRawPostData(json_encode($params))->post($url);
            //            $response = $curl->setRawPostData($dataString)->post($url);
        } else {
            $response = $curl->setGetParams($params)->get($url);
        }

        //Yii::$app->telegramChats->sendMessage($response);
        if (empty($response)) {
            return [];
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            Yii::warning('TBank API returned an invalid JSON response', 'payment');
            return [];
        }

        return $decoded;
    }

    /**
     * Generates token
     *
     * @param array $args array of query params
     *
     * @return string
     */
    private function _genToken($args)
    {
        $token = '';
        $args['Password'] = htmlspecialchars_decode($this->_secretKey);
        ksort($args);
        foreach ($args as $arg) {
            if (is_array($arg) || $arg === null) {
                continue;
            }

            if (is_bool($arg)) {
                $token .= $arg ? 'true' : 'false';
                continue;
            }

            $token .= (string)$arg;
        }

        return hash('sha256', $token);
    }

    /**
     * Verifies a signed T-Bank notification using only root-level fields.
     */
    public function isValidNotification(array $payload): bool
    {
        $receivedToken = $payload['Token'] ?? null;
        $terminalKey = $payload['TerminalKey'] ?? null;
        if (!is_string($receivedToken) || $receivedToken === ''
            || !is_string($terminalKey) || $terminalKey === ''
            || !hash_equals((string)$this->_terminalKey, $terminalKey)) {
            return false;
        }

        unset($payload['Token']);
        return hash_equals($this->_genToken($payload), strtolower($receivedToken));
    }

    /**
     * {@inheritdoc}
     */
    public function create($amount, $method, $description = null, $depositId = null, $currency = 'RUB', $methodCurrency = 'RUB', $email = null): array
    {
        $params = [
            'TerminalKey' => $this->_terminalKey,
            'Amount' => $amount * 100,
            'OrderId' => $depositId,
            'Description' => $description,
            'DATA' => [
                'Email' => $email,
            ],
            'Receipt' => [
                'Email' => $email,
                'Taxation' => 'osn',
                'Items' => [
                   [
                       "Name" => $description,
                       "Price" => $amount * 100,
                       "Quantity" => 1,
                       "Amount" => $amount * 100,
                       'PaymentMethod' => 'full_payment',
                       'PaymentObject' => 'service',
                       "Tax" => "none",
                   ]
                ],
            ],
        ];
        $notificationUrl = PaymentCallbackHandler::callbackUrlFor('tinkoff');
        if ($notificationUrl !== '') {
            $params['NotificationURL'] = $notificationUrl;
        }
        return $this->sendHttpRequest('POST', "Init", $params);
    }

    /**
     * {@inheritdoc}
     */
    public function check($depositId): array
    {
        $params = [
            'TerminalKey' => $this->_terminalKey,
            'OrderId' => $depositId,
        ];
        return $this->sendHttpRequest('POST', "CheckOrder", $params);
    }

    /**
     * Returns the authoritative state of one provider payment.
     */
    public function getState($paymentId): array
    {
        $params = [
            'TerminalKey' => $this->_terminalKey,
            'PaymentId' => $paymentId,
        ];
        return $this->sendHttpRequest('POST', 'GetState', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function cancel($depositId): array
    {
        $params = [
            'TerminalKey' => $this->_terminalKey,
            'PaymentId' => $depositId,
        ];
        return $this->sendHttpRequest('POST', "Cancel", $params);
    }
}
