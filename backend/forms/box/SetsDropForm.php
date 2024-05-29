<?php

namespace backend\forms\box;

use common\models\box\SetsDrop;
use Yii;

class SetsDropForm extends SetsDrop
{

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        if ($this->isNewRecord) {
            $this->created_at = date('Y-m-d H:i:s');
        }
        if (!$this->validate()) {
            return false;
        }

        if (!$this->save()) {
            throw new \Exception('Sets Drop not saved');
        }

        if (empty($this->id)) {
            $this->id = Yii::$app->db->getLastInsertID();
        }

        return true;
    }

}
