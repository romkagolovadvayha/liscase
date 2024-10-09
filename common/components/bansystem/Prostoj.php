<?php

namespace common\components\bansystem;

use common\components\oauth\Steam;
use common\models\invoice\Deposit;
use common\models\user\User;
use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;

class Prostoj
{

    private $_banList = [];

    public function banList() {
        self::serverBans();

        return $this->_banList;
    }

    private function serverBans() {
        /** @var User[] $users */
        $users = User::find()
            ->andWhere('banned_at IS NOT NULL')
            ->orderBy(['banned_at' => SORT_DESC])
            ->limit(30)
            ->all();

        foreach ($users as $item) {
            $this->_banList[] = $this->serialize([
                                                     'steam_id' => $item->steam_id,
                                                     'reason' => $item->ban_reason,
                                                     'date' => $item->banned_at,
                                                     'expireDate' => $item->unbanned_at,
                                                     'server' => null,
                                                     'project' => 'Простой',
                                                 ]);
        }
    }

    /**
     * @param $array
     */
    private function serialize($item) {
        if (!empty($item['reason'])) {
            $item['reason'] = ArrayHelper::getValue(User::getReasonList(), $item['reason']);
        } else {
            $item['reason'] = "Читы";
        }
        $expireDate = $item['expireDate'];
        if (empty($expireDate) || $expireDate == 0) {
            $item['expireDate'] = null;
        } else {
            $date = new \DateTime($expireDate);
            $item['expireDate'] = $date->format('Y-m-d H:i:s');
        }
        $date = new \DateTime($item['date']);
        $item['date'] = $date->format('Y-m-d H:i:s');

        return $item;
    }
}
