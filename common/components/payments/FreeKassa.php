<?php

namespace common\components\payments;

use linslin\yii2\curl\Curl;
use Yii;
use yii\base\Component;

/**
 * Tome Payment.
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'freeKassaApi'   => [
 *         'class' => \common\components\payments\FreeKassa::class,
 *         'secretKey' => '',
 *         'shop_id' => '',
 *     ]
 *     // ...
 * ]
 *
 * @author Roman Mescheryakov <rom_ik@bk.ru>
 * @since 1.0
 */
class FreeKassa
{

    public $secretKey;
    public $shop_id;

    /**
     * {@inheritdoc}
     */
    public $baseUrl = 'https://api.freekassa.ru/v1';

    /**
     * @var Curl
     */
    public $curl;

    /**
     * @param       $method
     * @param       $serviceUrl
     * @param array $params
     *
     * @return array
     * @throws \Exception
     */
    public function sendHttpRequest($method, $serviceUrl, $params = [])
    {
        $url = $this->baseUrl . $serviceUrl;
        sleep(1);
        $params['shopId'] = $this->shop_id;
        $params['nonce'] = time() + 1;
        ksort($params);
        $sign = hash_hmac('sha256', implode('|', $params), $this->secretKey);
        $params['signature'] = $sign;

        $dataString = json_encode($params, JSON_UNESCAPED_UNICODE);
        $curl = Yii::$app->curl
            ->setHeader('Content-Type', 'application/json');

        if ($method == 'POST') {
            $response = $curl->setRawPostData($dataString)->post($url);
        } else {
            $response = $curl->setGetParams($params)->get($url);
        }

        if (empty($response)) {
            return [];
        }
        return json_decode($response, 1);
    }

    /**
     * {@inheritdoc}
     */
    public function create($amount, $method, $description = null, $depositId = null): array
    {
        $params = [
            'paymentId' => $depositId,
            'i' => $method,
            'email' => 'rom_ik@bk.ru',
            'ip' => $_SERVER['REMOTE_ADDR'],
            'amount' => $amount,
            'currency' => 'RUB',
        ];
        return $this->sendHttpRequest('POST', '/orders/create', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function check($paymentId)
    {
        $params = [
            'orderId' => $paymentId,
        ];
        return $this->sendHttpRequest('POST', '/orders', $params);
    }
}
