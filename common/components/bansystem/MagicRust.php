<?php

namespace common\components\bansystem;

use common\components\oauth\Steam;
use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class MagicRust
{

    private $_banList = [];

    public function banList() {
        self::serverBans(null, 'Magic Rust');

        return $this->_banList;
    }

    private function serverBans($serverName, $projectName) {
        $server = $this->query();
        foreach ($server as $item) {
            $this->_banList[] = $this->serialize([
                'steam_id' => $item['steamid'],
                'reason' => $item['reason'],
                'date' => $item['time'],
                'expireDate' => null,
                'server' => $serverName,
                'project' => $projectName,
            ]);
        }
    }

    /**
     * @param $array
     */
    private function serialize($item) {
        $date = new \DateTime();
        $date->setTimestamp($item['date']);
        $item['date'] = $date->format('Y-m-d H:i:s');
        if (strpos(strtolower($item['reason']), 'cheat') !== false || strpos(strtolower($item['reason']), 'чит') !== false) {
            $item['reason'] = "Читы";
        }
        return $item;
    }

    private function query() {
        $cacheKey = 'banList_MagicRust';
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        try {
            $apiUrl = "https://vk.magicrust.ru/api/getBans";
            $response = json_decode(Yii::$app->curl->get($apiUrl), 1);
            Yii::$app->cache->set($cacheKey, $response, 3 * 60 * 60);
            return $response;
        } catch (\Exception $e) {
            Yii::$app->telegramReports->sendMessage("{$cacheKey}:" . $e->getLine() . ":" . $e->getMessage());
        }
        return [];
    }
}
