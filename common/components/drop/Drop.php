<?php

namespace common\components\drop;

use common\models\box\DropBlocked;
use common\models\invoice\Invoice;
use Yii;
use yii\db\Expression;

class Drop
{
    private $_blocked = [];
    private $_drop = [];
    private $_countBuy = [];

    private function getBlocked($serverId) {
        if (array_key_exists($serverId, $this->_blocked)) {
            return $this->_blocked[$serverId];
        }
        $this->_blocked[$serverId] = DropBlocked::getBlockedList($serverId);

        return $this->_blocked[$serverId];
    }

    public function getCountBuy($userId) {
        if (array_key_exists($userId, $this->_countBuy)) {
            return $this->_countBuy[$userId];
        }
        $date = new \DateTime();
        $date->modify('-3 day');
        $purchases = Invoice::find()
                                                        ->select(['drop_id', 'COUNT(*) AS purchases_count'])
                                                        ->where(['user_id' => $userId])
                                                        ->andWhere(['>=', 'created_at', $date->format('Y-m-d H:i:s')])
                                                        ->andWhere(['>=', 'created_at', '2025-07-31 21:00'])
                                                        ->groupBy('drop_id')
                                                        ->asArray()
                                                        ->all();

        $this->_countBuy[$userId] = [];
        foreach ($purchases as $item) {
            $this->_countBuy[$userId][$item['drop_id']] = $item['purchases_count'];
        }

        return $this->_countBuy[$userId];
    }

    public function clearCountBuy($userId) {
        if (!empty($this->_countBuy[$userId])) {
            unset($this->_countBuy[$userId]);
        }
    }

    private function getActiveDrop() {
        if (!empty($this->_drop)) {
            return $this->_drop;
        }
        $this->_drop = \common\models\box\Drop::getDropListAll();

        return $this->_drop;
    }

    public function getBlockedByDropId($serverId, $dropId) {
        $items = $this->getBlocked($serverId);
        if (empty($items[$dropId])) {
            return null;
        }
        return $items[$dropId];
    }

    public function getActiveDropById($dropId) {
        $items = $this->getActiveDrop();
        if (empty($items[$dropId])) {
            return null;
        }
        return $items[$dropId];
    }

}