<?php

namespace common\components\bansystem;

use common\components\oauth\Steam;
use common\models\invoice\Deposit;
use Yii;
use yii\base\Component;

class Moskov77
{

    private $_banList = [];

    public function banList() {
        self::serverBans('Московский для новичков', 'Moskov77');

        return $this->_banList;
    }

    private function serverBans($serverName, $projectName) {
        /** @var \common\models\statistics\Moskov77[] $bans */
        $bans = \common\models\statistics\Moskov77::find()->cache(3 * 60 * 60)->all();
        foreach ($bans as $item) {
            $this->_banList[] = [
                'steam_id' => $item->steamID,
                'reason' => $item->reason,
                'date' => $item->banTime,
                'expireDate' => null,
                'server' => $serverName,
                'project' => $projectName,
            ];
        }
    }

}
