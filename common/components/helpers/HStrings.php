<?php

namespace common\helpers;

class HStrings
{
    public static function short($str, $limit)
    {
        if (empty($str)) {
            return '';
        }
        if (mb_strlen($str) > $limit) {
            $str = mb_substr($str, 0, $limit - 3) . '...';
        }

        return $str;
    }

    public static function pluralForm($n, $forms) {
        return $n%10==1&&$n%100!=11?$forms[0]:($n%10>=2&&$n%10<=4&&($n%100<10||$n%100>=20)?$forms[1]:$forms[2]);
    }
}