<?php

namespace common\components\bansystem;

use common\components\oauth\Steam;
use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class Slabiy
{

    private $_banList = [];

    public function banList() {
        self::serverBans('slabiy1_banlist', 'Сервер #1', 'Слабый');
        self::serverBans('slabiy2_banlist', 'Сервер #2', 'Слабый');
        self::serverBans('slabiy3_banlist', 'Сервер #3', 'Слабый');

        return $this->_banList;
    }

    private function serverBans($serverTag, $serverName, $projectName) {
        $server = $this->query($serverTag);
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
        if (empty($expireDate) || $expireDate == 'Никогда') {
            $item['expireDate'] = null;
        } else {
            $date = new \DateTime($expireDate);
            $item['expireDate'] = $date->format('Y-m-d H:i:s');
        }
        $item['reason'] = str_replace('by morti', '', $item['reason']);
        if (strpos(strtolower($item['reason']), 'cheat') !== false || strpos(strtolower($item['reason']), 'чит') !== false) {
            $item['reason'] = "Читы";
        }
        $date = new \DateTime($item['date']);
        $item['date'] = $date->format('Y-m-d H:i:s');

        return $item;
    }

    private function query($stable = 'russian_banlist') {
        $cacheKey = 'banList_Slabiy' . $stable;
        if (Yii::$app->cache->get($cacheKey)) {
            return Yii::$app->cache->get($cacheKey);
        }
        try {
            $apiUrl = "https://rustaria.ru/slabiy/serverbanlist.php?table={$stable}";
            $response = json_decode(Yii::$app->curl->get($apiUrl), 1);
            Yii::$app->cache->set($cacheKey, $response, 59);
            return $response;
        } catch (\Exception $e) {
            Yii::$app->telegramReports->sendMessage("{$cacheKey}:" . $e->getLine() . ":" . $e->getMessage());
        }
        return [];
    }
}
