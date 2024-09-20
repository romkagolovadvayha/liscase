<?php

namespace frontend\forms\profile;

use common\models\user\User;
use Yii;

class RustruForm extends User
{

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        if ($this->rustru_activated) {
            return false;
        }
        $this->rustru_activated = 1;
        if (!$this->save()) {
            return false;
        }

        return true;
    }

}
