<?php

namespace common\components\helpers;

use Yii;

class CurrencyHelper
{
    /**
     * @return string
     */
    public static function default()
    {
        if (!Yii::$app->user->isGuest) {
            return Yii::$app->user->identity->getCurrency();
        }
        return 'RUR';
    }
}