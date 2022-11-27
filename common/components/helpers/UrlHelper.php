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

        if (strstr($url, 'education.digiu.') || strstr($url, 'education.test.digiu.')) {
            $layout = '@education/views/layouts/' . $layout;
        } elseif (strstr($url, 'wealth.digiu.') || strstr($url, 'wealth.test.digiu.')) {
            $layout = '@wealth/views/layouts/' . $layout;
        } elseif (strstr($url, 'translate.digiu.') || strstr($url, 'translate.test.digiu.')) {
            $layout = '@translate/views/layouts/' . $layout;
        }

        return $layout;
    }
}