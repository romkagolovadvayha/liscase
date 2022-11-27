<?php

namespace common\components\web;

use Yii;

class DeviceDetect extends \alexandernst\devicedetect\DeviceDetect
{
    public function isIos()
    {
        //Detect special conditions devices
        $iPod   = stripos($_SERVER['HTTP_USER_AGENT'], "iPod");
        $iPhone = stripos($_SERVER['HTTP_USER_AGENT'], "iPhone");
        $iPad   = stripos($_SERVER['HTTP_USER_AGENT'], "iPad");

        return $iPod || $iPhone || $iPad;
    }
}