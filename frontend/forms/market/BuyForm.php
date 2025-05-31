<?php

namespace frontend\forms\market;

use common\components\web\Cookie;
use common\models\box\Drop;
use common\models\promocode\Promocode;
use common\models\user\UserPromocode;
use Yii;
use yii\helpers\ArrayHelper;

class BuyForm extends Drop
{
    public $drop_id;
    /** @var Drop */
    public $drop;

    public function rules(): array
    {
        return ArrayHelper::merge([
                                      [['drop_id'], 'trim'],
                                      [['drop_id'], 'integer'],
                                      ['drop_id', 'validateDropId'],
                                  ], parent::rules());
    }

    public function afterFind()
    {
        parent::afterFind();
        if ($this->drop_type == Drop::TYPE_SELECT && empty($this->drop_id)) {
            $this->drop_id = $this->subDrops[0]->drop_id;
            $this->drop = $this->subDrops[0]->drop;
        }
    }

    /**
     * @param $attribute
     */
    public function validateDropId($attribute)
    {
        $this->drop = Drop::findOne($this->drop_id);
        if ($this->drop_type == Drop::TYPE_SELECT && (empty($this->drop) || $this->drop->status !== Drop::STATUS_ACTIVE)) {
            $this->drop_id = $this->subDrops[0]->drop_id;
            $this->drop = $this->subDrops[0]->drop;
            $this->addError($attribute, Yii::t('common', 'Товар не найден'));
        }
    }

}
