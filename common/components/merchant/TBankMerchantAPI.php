<?php

namespace common\components\merchant;

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
        return json_decode($response, 1);
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
            if(!is_array($arg))
                $token .= $arg;
        }
        $token = hash('sha256', $token);

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    public function create($amount, $method, $description = null, $depositId = null, $currency = 'RUB', $methodCurrency = 'RUB'): array
    {
        $params = [
            'TerminalKey' => $this->_terminalKey,
            'Amount' => $amount * 100,
            'OrderId' => $depositId,
            'Description' => $description,
            'DATA' => [
                'Email' => 'rom_ik@bk.ru',
                'Phone' => '+79821159607',
            ],
            'Receipt' => [
//                'Email' => 'rom_ik@bk.ru',
                'Phone' => '+79821159607',
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
