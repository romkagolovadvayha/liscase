<?php

namespace common\components\rusttm;

use linslin\yii2\curl\Curl;
use Yii;
use yii\base\Component;
use yii\web\NotFoundHttpException;

class RustTm
{

    /**
     * {@inheritdoc}
     */
    public $baseUrl = 'https://rust.tm/api/v2';

    /**
     * {@inheritdoc}
     */
    public function history(): array
    {
        $secretKey = Yii::$app->settings->get('rusttm_secretKey');
        $url = $this->baseUrl . "/history?key={$secretKey}";
        $response = Yii::$app->curl->get($url);
        return json_decode($response, 1);
    }

    /**
     * {@inheritdoc}
     */
    public function buy($name, $price, $partner, $token): array
    {
        $secretKey = Yii::$app->settings->get('rusttm_secretKey');
        $url = $this->baseUrl . "/buy-for?key={$secretKey}&hash_name=".urlencode($name)."&price={$price}&partner={$partner}&token={$token}";
        $response = Yii::$app->curl->get($url);
        Yii::$app->telegramChats->sendMessage($response);
        if (empty($response)) {
            sleep(2);
            $response = Yii::$app->curl->get($url);
            Yii::error('RustTm buy 2: ' .  $response);
        }
        if (empty($response)) {
            sleep(3);
            $response = Yii::$app->curl->get($url);
            Yii::error('RustTm buy 3: ' .  $response);
        }
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
