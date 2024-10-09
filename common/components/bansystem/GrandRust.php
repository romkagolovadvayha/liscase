<?php

namespace common\components\bansystem;

use common\components\oauth\Steam;
use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class GrandRust
{

    private $_banList = [];

    public function banList() {
        self::serverBans('Grand Rust');

        return $this->_banList;
    }

    private function serverBans($projectName) {
        $server = $this->query();
        $serversNames = $this->serverList();
        $count = 0;
        foreach ($server as $item) {
            $serverName = "Все сервера";
            if (!empty($item['serverID']) && !empty($serversNames[$item['serverID']])) {
                $serverName = $serversNames[$item['serverID']];
            }
            $this->_banList[] = $this->serialize([
                'steam_id' => $item['targetSteamID'],
                'reason' => $item['reason'],
                'date' => $item['banDate'],
                'expireDate' => $item['unbanDate'],
                'server' => $serverName,
                'project' => $projectName,
            ]);
            if ($count > 30) {
                break;
            }
            $count++;
        }
    }

    /**
     * @param $array
     */
    private function serialize($item) {
        $expireDate = $item['expireDate'];
        if (empty($expireDate) || $expireDate == 0) {
            $item['expireDate'] = null;
        } else {
            $date = new \DateTime();
            $date->setTimestamp($expireDate);
            $item['expireDate'] = $date->format('Y-m-d H:i:s');
        }
        if (strpos(strtolower($item['reason']), 'cheat') !== false || strpos(strtolower($item['reason']), 'чит') !== false) {
            $item['reason'] = "Читы";
        }
        $date = new \DateTime();
        $date->setTimestamp($item['date']);
        $item['date'] = $date->format('Y-m-d H:i:s');

        return $item;
    }

    private function query() {
        $cacheKey = 'banList_Grand_Rust';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        try {
            $apiUrl = "https://shop.grand-rust.ru/api/index.php";
            $params = [
                'modules' => 'banlist',
                'action' => 'getData',
                'serverID' => 30,
            ];
            $response = json_decode(Yii::$app->curl->setRawPostData($params)->post($apiUrl), 1)['data'];
            Yii::$app->cache->set($cacheKey, $response, 3 * 60 * 60);
            return $response;
        } catch (\Exception $e) {
            Yii::$app->telegramReports->sendMessage("{$cacheKey}:" . $e->getLine() . ":" . $e->getMessage());
        }
        return [];
    }

    private function serverList() {
        $cacheKey = 'banList_GrandRust_serverList';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        try {
            $apiUrl = "https://shop.grand-rust.ru/api/index.php";
            $params = [
                'modules' => 'monitoring',
                'action' => 'getServers'
            ];
            $response = json_decode(Yii::$app->curl->setRawPostData($params)->post($apiUrl), 1)['data'];

            $servers = [];
            foreach ($response as $item) {
                $servers[$item['id']] = str_replace("Grand Rust ", "", $item['hostname']);
            }

            Yii::$app->cache->set($cacheKey, $servers, 12 * 60 * 60);
            return $servers;
        } catch (\Exception $e) {
            Yii::$app->telegramReports->sendMessage("{$cacheKey}:" . $e->getLine() . ":" . $e->getMessage());
        }
        return [];
    }
}
