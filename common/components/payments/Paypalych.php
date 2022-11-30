<?php

namespace common\components\payments;

use linslin\yii2\curl\Curl;
use Yii;
use yii\base\Component;

/**
 * Paypalych Payment.
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'paypalychApi'   => [
 *         'class' => \common\components\payments\Paypalych::class,
 *         'secretKey' => '',
 *         'shop_id' => '',
 *     ]
 *     // ...
 * ]
 *
 * @author Roman Mescheryakov <rom_ik@bk.ru>
 * @since 1.0
 */
class Paypalych
{

    public $secretKey;
    public $shop_id;

    /**
     * {@inheritdoc}
     */
    public $baseUrl = 'https://paypalych.com/api/v1';

    /**
     * @var Curl
     */
    public $curl = 'https://paypalych.com/api/v1';


    /**
     * {@inheritdoc}
     */
    protected function defaultName()
    {
        return 'steam';
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $this->curl = Yii::$app->curl;
        $this->setHeader('Authorization', 'Bearer ' . $this->secretKey);
        $this->setHeader('Content-Type', 'application/json;charset=UTF-8');
    }

    /**
     * {@inheritdoc}
     */
    public function create($orderName, $amount, $description): array
    {
        $url = $this->baseUrl . "/bill/create";
        $body = [
            'amount' => $amount,
            'order_id' => $orderName,
            'description' => $description,
            'type' => 'normal',
            'shop_id' => $this->shop_id,
            'currency_in' => 'RUB',
            'payer_pays_commission' => '1',
            'name' => 'Платёж',
        ];
        $response = $this->curl->post($url, json_encode($body, JSON_UNESCAPED_UNICODE));
        return json_decode($response, 1);
    }
}
