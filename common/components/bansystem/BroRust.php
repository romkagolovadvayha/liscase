<?php

namespace common\components\bansystem;

use common\components\oauth\Steam;
use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class BroRust
{

    private $_banList = [];

    public function banList() {
        self::serverBans('BroRust');

        return $this->_banList;
    }

    private function serverBans($projectName) {
        $server = $this->query();
        foreach ($server as $item) {
            $serverName = str_replace('BroRust ', '', $item['Server']);
            if ($item['AllServers'] == 1) {
                $serverName = null;
            }
            $this->_banList[] = $this->serialize([
                'steam_id' => $item['UserID'],
                'reason' => $item['BanReason'],
                'date' => $item['BanDate'],
                'expireDate' => $item['UnBanDate'],
                'server' => $serverName,
                'project' => $projectName,
            ]);
        }
    }

    /**
     * @param $array
     */
    private function serialize($item) {
        $expireDate = $item['expireDate'];
        if (empty($expireDate)) {
            $item['expireDate'] = null;
        } else {
            $date = new \DateTime($expireDate);
            $item['expireDate'] = $date->format('Y-m-d H:i:s');
        }
        $date = new \DateTime($item['date']);
        $item['date'] = $date->format('Y-m-d H:i:s');

        return $item;
    }

    private function query() {
        $cacheKey = 'banList_BroRust';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        try {
            $apiUrl = "https://api.brorust.com/server/ban-list?page=1&linePerPage=30";
            $response = json_decode(file_get_contents(__DIR__ . '/files/broRust.json'), 1)['success']['payload']['data'];
            Yii::$app->cache->set($cacheKey, $response, 59);
            return $response;
        } catch (\Exception $e) {
            Yii::$app->telegramReports->sendMessage("{$cacheKey}:" . $e->getLine() . ":" . $e->getMessage());
        }
        return [];
    }
}
