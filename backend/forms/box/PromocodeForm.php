<?php

namespace backend\forms\box;

use common\models\box\DropImage;
use common\models\promocode\Promocode;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

class PromocodeForm extends Promocode
{

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        if ($this->isNewRecord) {
            $this->status = 1;
            $this->created_at = date('Y-m-d H:i:s');
        }
        $this->type = 2;
        if (!$this->validate()) {
            return false;
        }

        if (!$this->save()) {
            throw new \Exception('Promocode not saved');
        }

        if (empty($this->id)) {
            $this->id = Yii::$app->db->getLastInsertID();
        }

        return true;
    }

}
