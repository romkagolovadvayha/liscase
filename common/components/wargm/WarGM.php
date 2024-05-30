<?php

namespace common\components\wargm;

use Yii;

class WarGM
{
    public $apiKey;
    public $baseUrl;

    /**
     * @param       $method
     * @param       $serviceUrl
     * @param array $params
     *
     * @return array
     * @throws \Exception
     */
    public function sendHttpRequest($method, $serviceUrl, $params = null)
    {
        $url = $this->baseUrl . $serviceUrl;
        $body = null;
        if (!empty($params)) {
            $body = json_encode($params);
        }
        $curl = Yii::$app->curl
            ->setRequestBody($body);

        if ($method === 'POST') {
            $response = $curl->post($url, false);
        } else {
            $response = $curl->get($url, false);
        }
        if (empty($response)) {
            return [];
        }
        return $response;
    }

    /**
     * @param $serverId
     *
     * @return array
     */
    public function getVotes($serverId): array
    {
        $url = "/server/votes?client={$serverId}:{$this->apiKey}";
        return $this->sendHttpRequest('GET', $url);
    }


}