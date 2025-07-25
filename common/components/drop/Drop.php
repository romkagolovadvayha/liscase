<?php

namespace common\components\drop;

use common\models\box\DropBlocked;
use Yii;

class Drop
{
    private $_blocked = [];
    private $_drop = [];

    private function getBlocked($serverId) {
        if (array_key_exists($serverId, $this->_blocked)) {
            return $this->_blocked[$serverId];
        }
        $this->_blocked[$serverId] = DropBlocked::getBlockedList($serverId);

        return $this->_blocked[$serverId];
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