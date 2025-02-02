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
    public function send($channelId, $title, $description, $image, $fields, $tokenBot)
    {
        $embeds = [
            'description' => $description,
            'color' => 5793266,
            'url' => "",
            'footer' => (object)[],
        ];
        if (!empty($title)) {
            $embeds['title'] = $title;
        }
        if (!empty($fields)) {
            $embeds['fields'] = $fields;
        }
        if (!empty($image)) {
            $embeds['thumbnail'] = [
              'url' => $image
            ];
        }
        $params = [
            'embeds' => [$embeds],
        ];

        $response = (clone Yii::$app->curl)
            ->setOption(CURLOPT_TIMEOUT, 10)
            ->setOption(CURLOPT_POSTFIELDS, json_encode($params))
            ->setHeader('Content-Type', 'application/json')
            ->setHeader('Authorization', "Bot {$tokenBot}")
            ->post("https://discord.com/api/v10/channels/{$channelId}/messages");

        return $response;
    }
}
