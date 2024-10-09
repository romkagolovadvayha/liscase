<?php

namespace common\components\bansystem;

use common\components\oauth\Steam;
use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class RustUssr
{

    private $_banList = [];

    public function banList() {
        self::serverBans(null, 'Rust USSR');

        return $this->_banList;
    }

    private function serverBans($serverName, $projectName) {
        $server = $this->query();
        $count = 0;
        foreach ($server as $steamId => $item) {
            $this->_banList[] = $this->serialize([
                'steam_id' => $steamId,
                'reason' => $item['Reason'],
                'date' => $item['BanDate'],
                'expireDate' => $item['ExpireDate'],
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
        if (empty($expireDate) || $expireDate == 'Бан навсегда') {
            $item['expireDate'] = null;
        } else {
            $date = new \DateTime($expireDate);
            $item['expireDate'] = $date->format('Y-m-d H:i:s');
        }
        if (strpos(strtolower($item['reason']), 'cheat') !== false || strpos(strtolower($item['reason']), 'чит') !== false) {
            $item['reason'] = "Читы";
        }
        $date = new \DateTime($item['date']);
        $item['date'] = $date->format('Y-m-d H:i:s');

        return $item;
    }

    private function query() {
        $cacheKey = 'banList_RustUSSR';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        try {
            $apiUrl = "https://rustussr.ru/serverbanlist.php";
            $response = json_decode(Yii::$app->curl->get($apiUrl), 1);
            Yii::$app->cache->set($cacheKey, $response, 3 * 60 * 60);
            return $response;
        } catch (\Exception $e) {
            Yii::$app->telegramReports->sendMessage("{$cacheKey}:" . $e->getLine() . ":" . $e->getMessage());
        }
        return [];
    }
}
