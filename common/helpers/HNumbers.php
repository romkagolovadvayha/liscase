<?php

namespace common\helpers;

class HNumbers
{
    /**
     * Format number with K/M/B suffix (1000 -> 1K, 1500000 -> 1.5M)
     *
     * @param int|float $number
     * @param int $precision Decimal precision
     * @return string
     */
    public static function shortFormat($number, $precision = 1)
    {
        if ($number < 1000) {
            return (string)$number;
        }

        if ($number < 1000000) {
            return round($number / 1000, $precision) . 'K';
        }

        if ($number < 1000000000) {
            return round($number / 1000000, $precision) . 'M';
        }

        return round($number / 1000000000, $precision) . 'B';
    }

    /**
     * Format number with thousands separator
     *
     * @param int|float $number
     * @param int $decimals
     * @return string
     */
    public static function format($number, $decimals = 0)
    {
        return number_format($number, $decimals, '.', ' ');
    }
}











































