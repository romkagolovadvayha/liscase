<?php

namespace frontend\forms\market;

use common\models\box\Drop;
use common\models\box\Select;
use common\models\user\User;
use Yii;
use yii\helpers\ArrayHelper;

class BuySelectForm extends Select
{
    public $drop_id;
    public $drop;

    public function rules(): array
    {
        return ArrayHelper::merge([
            [['drop_id'], 'required'],
            [['drop_id'], 'trim'],
            [['drop_id'], 'integer'],
            ['drop_id', 'validateDropId'],
        ], parent::rules());
    }

    public function afterFind()
    {
        parent::afterFind();
        if (empty($this->drop_id)) {
            $this->drop_id = $this->selectDrop[0]->drop_id;
            $this->drop = $this->selectDrop[0]->drop;
        }
    }

    /**
     * @param $attribute
     */
    public function validateDropId($attribute)
    {
        $this->drop = Drop::findOne($this->drop_id);
        if (empty($this->drop) || $this->drop->status !== Drop::STATUS_ACTIVE) {
            $this->drop_id = $this->selectDrop[0]->drop_id;
            $this->drop = $this->selectDrop[0]->drop;
            $this->addError($attribute, Yii::t('common', 'Товар не найден'));
        }
    }

}
