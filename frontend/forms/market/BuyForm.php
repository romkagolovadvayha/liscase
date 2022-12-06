<?php

namespace frontend\forms\market;

use common\components\web\Cookie;
use common\models\box\Drop;
use common\models\promocode\Promocode;
use common\models\user\UserPromocode;
use Yii;

class BuyForm extends Drop
{

    public function rules(): array
    {
        return [
            [['code'], 'required'],
            [['code'], 'trim'],
            [['code'], 'string', 'max' => 255],
        ];
    }

    /**
     * @return bool
     */
    public function filter(): bool
    {

        return true;
    }

}
