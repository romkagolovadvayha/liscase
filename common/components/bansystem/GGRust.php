<?php

namespace common\components\bansystem;

use common\components\oauth\Steam;
use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class GGRust
{

    private $_banList = [];

    public function banList() {
        self::serverBans('russian_banlist', 'Российский', 'GGRust');
        self::serverBans('moscow_banlist', 'Московксий', 'GGRust');
        self::serverBans('krasnodar_banlist', 'Краснодарский', 'GGRust');
        self::serverBans('piter_banlist', 'Классика X3', 'GGRust');
        self::serverBans('made_in_russia_banlist', 'Классика X2', 'GGRust');
        self::serverBans('vanilla_banlist', 'Vanila 2X2', 'GGRust');

        return $this->_banList;
    }

    private function serverBans($serverTag, $serverName, $projectName) {
        $server = $this->query($serverTag);
        foreach ($server as $steamId => $item) {
            $this->_banList[] = $this->serialize([
                'steam_id' => $steamId,
                'reason' => $item['Reason'],
                'date' => $item['BanDate'],
                'expireDate' => $item['ExpireDate'],
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
        if (empty($expireDate) || $expireDate == 'Никогда') {
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

    private function query($stable = 'russian_banlist') {
        $cacheKey = 'banList_GGRust' . $stable;
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        try {
            $apiUrl = "https://stats.ggrust.ru/serverbanlist.php?table={$stable}";
            $response = json_decode(Yii::$app->curl->get($apiUrl), 1);
            Yii::$app->cache->set($cacheKey, $response, 3 * 60 * 60);
            return $response;
        } catch (\Exception $e) {
            Yii::$app->telegramReports->sendMessage("{$cacheKey}:" . $e->getLine() . ":" . $e->getMessage());
        }
        return [];
    }
}
