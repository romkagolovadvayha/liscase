<?php

namespace common\components\bansystem;

use common\components\oauth\Steam;
use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class JokerRust
{

    private $_banList = [];

    public function banList() {
        self::serverBans('Joker Rust');

        return $this->_banList;
    }

    private function serverBans($projectName) {
        /** @var \common\models\statistics\JokerRust[] $bans */
        $bans = \common\models\statistics\JokerRust::find()->cache(3 * 60 * 60)->all();
        foreach ($bans as $item) {
            $data = json_decode($item->data3, 1);
            $timeData = explode(' ', $data[0]['time']);
            $time = $timeData[0] . ":00";
            $date = str_replace(':', '.', $timeData[1]) . " " . $time;
            $date = new \DateTime($date);
            $serverName = "#1";
            if ($item->server == "RUSTJOKER_2") {
                $serverName = "#2";
            }
            if ($item->server == "RUSTJOKER_3") {
                $serverName = "#3";
            }
            if ($item->server == "RUSTJOKER_4") {
                $serverName = "#4";
            }
            $this->_banList[] = [
                'steam_id' => $item->steam_id,
                'reason' => $item->reason,
                'date' => $date->format('Y-m-d H:i:s'),
                'expireDate' => null,
                'server' => $serverName,
                'project' => $projectName,
            ];
        }
    }

}
