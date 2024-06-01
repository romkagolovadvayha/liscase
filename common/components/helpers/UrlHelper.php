<?php

namespace common\components\helpers;

use Yii;

class UrlHelper
{
    /**
     * @param string $layout
     *
     * @return string
     */
    public static function getLayoutByUrl($layout)
    {
        $url = Yii::$app->request->absoluteUrl;

        return $layout;
    }
}