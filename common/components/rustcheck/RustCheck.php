<?php

namespace common\components\rustcheck;

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
     * @param $steamId
     *
     * @return array|null
     * @throws \Exception
     */
    public function getInfo($steamId)
    {
        $url = $this->baseUrl . "?action=getInfo&key={$this->secretKey}&player={$steamId}";
        $response = Yii::$app->curl->get($url);
        if (empty($response)) {
            return [];
        }
        return json_decode($response, 1);
    }

    /**
     * {@inheritdoc}
     */
    public function ban($steamId, $reason)
    {
        $url = $this->baseUrl . "?action=addBan&key={$this->secretKey}&player={$steamId}&reason={$reason}";
        Yii::$app->curl->get($url);
    }

    /**
     * {@inheritdoc}
     */
    public function unban($steamId)
    {
        $url = $this->baseUrl . "?action=removeBan&key={$this->secretKey}&player={$steamId}";
        Yii::$app->curl->get($url);
    }

}
