<?php

namespace common\components\rustcheck;

use linslin\yii2\curl\Curl;
use Yii;
use yii\base\Component;
use yii\web\NotFoundHttpException;

class RustCheck
{

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
        $secretKey = Yii::$app->settings->get('banSystem_rustcheatcheck');
        $url = $this->baseUrl . "?action=getInfo&key={$secretKey}&player={$steamId}";
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
        $secretKey = Yii::$app->settings->get('banSystem_rustcheatcheck');
        $url = $this->baseUrl . "?action=addBan&key={$secretKey}&player={$steamId}&reason={$reason}";
        Yii::$app->curl->get($url);
    }

    /**
     * {@inheritdoc}
     */
    public function unban($steamId)
    {
        $secretKey = Yii::$app->settings->get('banSystem_rustcheatcheck');
        $url = $this->baseUrl . "?action=removeBan&key={$secretKey}&player={$steamId}";
        Yii::$app->curl->get($url);
    }

}
