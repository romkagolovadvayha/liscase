<?php

namespace common\components\web;

use Yii;

class GeoIp
{
    /**
     * @return string
     */
    public static function getCountry()
    {
        $userIp   = Yii::$app->request->userIP;
        $location = Yii::$app->geoip->lookupLocation($userIp);

//        Yii::error('$userIp = ' . $userIp, 'error');
//        Yii::error('$location = ' . ($location ? $location->countryCode : 'empty'), 'error');

        if (empty($location)) {
            return 'RU';
        }

        return $location->countryCode;
    }

    /**
     * @return bool
     */
    public static function getIsRussia()
    {
        $countryCode = self::getCountry();

        return in_array($countryCode, ['AZ', 'AM', 'BY', 'KZ', 'KG', 'MD', 'TJ', 'TM', 'UZ', 'UA', 'RU']);
    }
}