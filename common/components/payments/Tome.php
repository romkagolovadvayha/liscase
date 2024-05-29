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
 *     'tomeApi'   => [
 *         'class' => \common\components\payments\Tome::class,
 *         'secretKey' => '',
 *         'shop_id' => '',
 *     ]
 *     // ...
 * ]
 *
 * @author Roman Mescheryakov <rom_ik@bk.ru>
 * @since 1.0
 */
class Tome
{

    public $secretKey;
    public $shop_id;

    /**
     * {@inheritdoc}
     */
    public $baseUrl = 'https://tome.ge/api/v1';

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

        $dataString = json_encode($params, JSON_UNESCAPED_UNICODE);
        $curl = Yii::$app->curl
            ->setHeader('Authorization', 'Basic ' . base64_encode($this->shop_id . ':' . $this->secretKey))
            ->setHeader('Idempotency-Key', uniqid())
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
    public function create($amount, $method, $description, $depositId): array
    {
        $params = [
            'amount' => [
                'value' => $amount . "",
                'currency' => 'RUB'
            ],
            "customer" => [
                "settlement_method" => $method
            ],
            "confirmation" => [
                "type" => "redirect",
                "return_url" => "https://a.prostoj.store/user/history?depositId={$depositId}"
            ],
            'description' => $description
        ];
        return $this->sendHttpRequest('POST', '/payments', $params);
    }

    /**
     * {@inheritdoc}
     */
    public function check($paymentId): array
    {
        return $this->sendHttpRequest('GET', '/payments/' . $paymentId);
    }
}
