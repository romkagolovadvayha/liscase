<?php

namespace common\components\discord;

use linslin\yii2\curl\Curl;
use Yii;
use yii\base\Component;
use yii\web\NotFoundHttpException;

class Discord
{
    /**
     * {@inheritdoc}
     */
    public function send($webhook, $title, $description, $image = null)
    {
        $embeds = [
            'title' => $title,
            'description' => $description,
            'color' => 5793266,
            'url' => "",
            'footer' => (object)[],
        ];
        if (!empty($image)) {
            $embeds['thumbnail'] = [
              'url' => $image
            ];
        }
        $params = [
            'username' => 'SkinDrops',
            'avatar_url' => 'https://i.imgur.com/UuFJB1B.png',
            'embeds' => [$embeds],
        ];

        Yii::$app->curl
            ->setOption(CURLOPT_TIMEOUT, 10)
            ->setOption(CURLOPT_POSTFIELDS, json_encode($params))
            ->setHeader('Content-Type', 'application/json')
            ->post($webhook);
    }
}
