<?php

namespace common\components\rusttm;

use linslin\yii2\curl\Curl;
use Yii;
use yii\base\Component;
use yii\web\NotFoundHttpException;

class RustCheck
{

    public $secretKey;

    /**
     * {@inheritdoc}
     */
    public $baseUrl = 'https://rustcheatcheck.ru/panel/api';

    /**
     * {@inheritdoc}
     */
    public function getInfo($steamId): array
    {
        $url = $this->baseUrl . "?action=getInfo&key={$this->secretKey}&player={$steamId}";
        $response = Yii::$app->curl->get($url);
        return json_decode($response, 1);
    }

}
