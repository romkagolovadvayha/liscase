<?php

namespace common\components\steam;

use Yii;

class MarketApi
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

//    /**
//     * @param $userId
//     * @param $levelName
//     *
//     * @return string|null
//     * @throws \Exception
//     */
//    public function getAccessToken($userId, $levelName)
//    {
//        $url = "/resources/accessTokens?userId=$userId&levelName=$levelName";
//        $response = $this->sendHttpRequest('POST', $url);
//        if (empty($response) || empty($response['token'])) {
//            return null;
//        }
//        return $response['token'];
//    }
//
    /**
     * @param $hashNames
     *
     * @return array
     * @throws \Exception
     */
    public function getSearchListItems($hashNames): array
    {
        $url = "/search-list-items-by-hash-name-all?";
        foreach ($hashNames as $hashName) {
            $url .= "&list_hash_name[]=" . urlencode($hashName);
        }
        $url .= "&key={$this->apiKey}";
        return $this->sendHttpRequest('GET', $url);
    }

    /**
     * @param $hashNames
     *
     * @return array
     * @throws \Exception
     */
    public function getListItemsinfo($hashNames): array
    {
        $url = "/get-list-items-info?";
        foreach ($hashNames as $hashName) {
            $url .= "&list_hash_name[]=" . urlencode($hashName);
        }
        $url .= "&key={$this->apiKey}";
        return $this->sendHttpRequest('GET', $url);
    }

    /**
     * @param $id
     *
     * @return array
     * @throws \Exception
     */
    public function getParserItemById($id): array
    {
        $url = "https://market.csgo.com/api/ItemInfo/{$id}/ru/?key=2gCOCfIiIu4V74f9763v5SjV7jyjT45";
        $response = Yii::$app->curl->get($url, false);
        if (empty($response)) {
            return [];
        }
        return $response;
    }
//
//    /**
//     * @param $userId
//     * @param $levelName
//     *
//     * @return string
//     * @throws \Exception
//     */
//    public function createApplicant($userId, $levelName)
//    {
//        $url = "/resources/applicants?levelName=$levelName";
//        $params = ['externalUserId' => $userId];
//        $response = $this->sendHttpRequest('POST', $url, $params);
//        if (empty($response) || empty($response['id'])) {
//            return null;
//        }
//        return $response['id'];
//    }
//
//    /**
//     * @param $applicantId
//     *
//     * @return string
//     * @throws \Exception
//     */
//    public function getApplicantStatus($applicantId)
//    {
//        $url = "/resources/applicants/$applicantId/requiredIdDocsStatus";
//        $response = $this->sendHttpRequest('GET', $url);
//        print_r($response);
//        if (empty($response) || empty($response['id'])) {
//            return null;
//        }
//        return $response['id'];
//    }

}