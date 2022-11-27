<?php

namespace common\components\helpers;

class DateHelper
{
    /**
     * @param \DateTime $dateTime
     * @param int       $months
     *
     * @return \DateTime
     */
    public static function addMonth(\DateTime $dateTime, $months = 1)
    {
        $aDate   = clone $dateTime;
        $origDay = $aDate->format('d');
        $aDate->modify('+' . $months . ' months');
        while ($aDate->format('d') < $origDay && $aDate->format('d') < 5) {
            $aDate->modify('-1 day');
        }

        return $aDate;
    }

    /**
     * @param \DateTime $dateTime
     * @param int       $months
     *
     * @return \DateTime
     */
    public static function minusMonth(\DateTime $dateTime, $months = 1)
    {
        $aDate   = clone $dateTime;
        $origDay = $aDate->format('d');
        $aDate->modify('-' . $months . ' months');
        while ($aDate->format('d') < $origDay && $aDate->format('d') < 5) {
            $aDate->modify('-1 day');
        }

        return $aDate;
    }

    /**
     * @param string      $dateFrom
     * @param string|null $dateTo
     * @param bool        $includeDateTo
     * @param bool        $dayKey
     *
     * @return array
     */
    public static function getDateList($dateFrom, $dateTo = null, $includeDateTo = true, $dayKey = false)
    {
        if (empty($dateTo) || strtotime($dateTo) > time()) {
            $dateTo = date('Y-m-d');
        }

        $date = $dateFrom;

        $array = [];
        while (true) {
            if ($includeDateTo) {
                if ($date > $dateTo) {
                    break;
                }

            } else {
                if ($date >= $dateTo) {
                    break;
                }
            }

            if ($dayKey) {
                $array[$date] = $date;

            } else {
                $array[] = $date;
            }

            $date = date('Y-m-d', strtotime($date . ' +1 day'));
        }

        return $array;
    }

    /**
     * @param string $dateFrom
     * @param string $dateTo
     * @param bool   $includeDateTo
     *
     * @return array
     */
    public static function getMonthList($dateFrom, $dateTo, $includeDateTo = true)
    {
        $date = $dateFrom;

        $array = [];
        while (true) {
            if ($includeDateTo) {
                if ($date > $dateTo) {
                    break;
                }

            } else {
                if ($date >= $dateTo) {
                    break;
                }
            }

            $array[$date] = $date;

            $date = date('Y-m-01', strtotime($date . ' +1 month'));
        }

        return $array;
    }
}