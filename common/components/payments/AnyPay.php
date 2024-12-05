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
 *     'anyPayApi'   => [
 *         'class' => \common\components\payments\AnyPay::class,
 *         'secretKey' => '',
 *         'shop_id' => '',
 *     ]
 *     // ...
 * ]
 *
 * @author Roman Mescheryakov <rom_ik@bk.ru>
 * @since 1.0
 */
class AnyPay
{

    public $secretKey;
    public $shop_id;
    public $api_id;

    /**
     * {@inheritdoc}
     */
    public $baseUrl = 'https://anypay.io/api';

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
        $signParams = $params;
        unset($signParams['email']);
        unset($signParams['method_currency']);
        unset($signParams['trans_id']);
//        $hash = md5($SysValue['anypay']['merchant_id'].':'.$_REQUEST['amount'].':'.$_REQUEST['pay_id'].':'.$SysValue['anypay']['secret_key']);
//        $sign = hash_hmac('sha256', str_replace('/', '', $serviceUrl) . implode('', $signParams) . $this->secretKey, $this->secretKey);
        $sign = hash('sha256', str_replace('/', '', $serviceUrl) . implode('', $signParams) . $this->secretKey);
//        print_r(str_replace('/', '', $serviceUrl) . implode('', $signParams) . $this->secretKey);exit;
        //currency:amount:secret_key:merchant_id:pay_id
//        $sign = md5(substr(str_replace('/', ':', $serviceUrl) , 1) . ":" . implode(':', $signParams) . ":" . $this->secretKey);
//        $sign = md5($signParams['currency'] . ":" . $signParams['amount'] . ":" . $this->secretKey . ":" . $this->shop_id . ":" . $signParams['pay_id']);
//        $sign = md5($this->shop_id . ":" . $signParams['pay_id'] . ':' . $this->secretKey);
//        print_r(substr(str_replace('/', ':', $serviceUrl) , 1) . ":" . implode(':', $signParams) . ":" . $this->secretKey);exit;
        $params['sign'] = $sign;
//print_r($params);exit;
//        $dataString = json_encode($params, JSON_UNESCAPED_UNICODE);
//        print_r($params);exit;
        $curl = Yii::$app->curl
            ->setHeader('Content-Type', 'multipart/form-data');

        if ($method == 'POST') {
            $response = $curl->setRawPostData($params)->post($url);
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
     * {@inheritdoc}
     */
    public function create($amount, $method, $description = null, $depositId = null, $currency = 'RUB', $methodCurrency = 'RUB'): array
    {
        $params = [
            'project_id' => $this->shop_id,
            'pay_id' => $depositId,
            'amount' => $amount,
            'currency' => $currency,
            'desc' => $description,
            'email' => 'rom_ik@bk.ru',
            'method' => $method,
            'method_currency' => $methodCurrency,
        ];
        return $this->sendHttpRequest('POST', "/create-payment/{$this->api_id}", $params);
    }

    /**
     * {@inheritdoc}
     */
    public function check($paymentId)
    {
        $params = [
            'project_id' => $this->shop_id,
            'trans_id' => $paymentId,
        ];
        return $this->sendHttpRequest('POST', "/payments/{$this->api_id}", $params);
    }
}
