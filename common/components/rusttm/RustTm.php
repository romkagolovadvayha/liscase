<?php

namespace common\components\rusttm;

use linslin\yii2\curl\Curl;
use Yii;
use yii\base\Component;
use yii\web\NotFoundHttpException;

class RustTm
{

    public $secretKey;

    /**
     * {@inheritdoc}
     */
    public $baseUrl = 'https://rust.tm/api/v2';

    /**
     * {@inheritdoc}
     */
    public function history(): array
    {
        $url = $this->baseUrl . "/history?key={$this->secretKey}";
        $response = Yii::$app->curl->get($url);
        return json_decode($response, 1);
    }

    /**
     * {@inheritdoc}
     */
    public function buy($name, $price, $partner, $token): array
    {
        $url = $this->baseUrl . "/buy-for?key={$this->secretKey}&hash_name=$name&price={$price}&partner={$partner}&token={$token}";
        $response = Yii::$app->curl->get($url);
        Yii::error('RustTm buy: ' .  $response);
        return json_decode($response, 1);
    }

    /**
     * {@inheritdoc}
     */
    public function prices(): array
    {
        $url = $this->baseUrl . "/prices/class_instance/RUB.json";
        $response = Yii::$app->curl->get($url);
        return json_decode($response, 1);
    }
}
