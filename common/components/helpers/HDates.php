<?php

namespace common\helpers;

use yii\helpers\ArrayHelper;

class HDates
{
    const SECONDS_1_MINUTE = 60;      // 1 минута
    const SECONDS_2_MINUTES = 120;      // 2 минуты
    const SECONDS_3_MINUTES = 180;      // 3 минуты
    const SECONDS_4_MINUTES = 240;      // 4 минуты
    const SECONDS_5_MINUTES = 300;    // 5 минут
    const SECONDS_10_MINUTES = 600;   // 10 минут
    const SECONDS_1_HOUR = 3600;      // 1 час
    const SECONDS_1_DAY = 86400;      // 1 день (24 часа)
    const SECONDS_2_DAYS = 172800;    // 48 часов
    const SECONDS_3_DAYS = 259200;    // 72 часа
    const SECONDS_7_DAYS = 604800;    // 7 дней
    const SECONDS_30_DAYS = 2592000;  // 30 дней
    const SECONDS_31_DAYS = 2678400;  // 31 день

    const HOURS_1_DAY = 24;
    const HOURS_1_WEEK = 168;


    /**
     * Возвращает дату в формате Y-m-d H:i:s
     * @param mixed $timestamp - null, timestamp или string
     * @return string
     */
    public static function long($timestamp = null)
    {
        return date("Y-m-d H:i:s", HDates::prepareTimestamp($timestamp));
    }

    /**
     * Возвращает дату в формате Y-m-d
     * @param mixed $timestamp - null, timestamp или string
     * @return string
     */
    public static function short($timestamp = null)
    {
        return date("Y-m-d", HDates::prepareTimestamp($timestamp));
    }

    /**
     * Возвращает дату в формате d.m.Y
     * @param mixed $timestamp - null, timestamp или string
     * @return string
     */
    public static function shortRus($timestamp = null)
    {
        return date("d.m.Y", HDates::prepareTimestamp($timestamp));
    }

    /**
     * Возвращает первый день месяца в формате Y-m-d
     * @param mixed $timestamp - null, timestamp или string
     * @return string
     */
    public static function first($timestamp = null)
    {
        return date("Y-m-01", HDates::prepareTimestamp($timestamp));
    }

    /**
     * Возвращает последний день месяца в формате Y-m-d
     * @param mixed $timestamp - null, timestamp или string
     * @return string
     */
    public static function last($timestamp = null)
    {
        return date("Y-m-t", HDates::prepareTimestamp($timestamp));
    }

    /**
     * Возвращает дату в формате Y-m-d H:i
     * @param mixed $timestamp - null, timestamp или string
     * @return string
     */
    public static function ui($timestamp = null)
    {
        return date("Y-m-d H:i", HDates::prepareTimestamp($timestamp));
    }

    /**
     * Проверяет формат UNIX Timestamp
     *
     * @param $timestamp
     * @return bool
     */
    public static function isTimestamp($timestamp)
    {
        return ((string) (int) $timestamp === (string) $timestamp);
        //	&& ($timestamp <= PHP_INT_MAX)
        //	&& ($timestamp >= ~PHP_INT_MAX)
        //	&& (!strtotime($timestamp));
    }

    /**
     * Возвращает дату в формате UNIX Timestamp
     * @param mixed $timestamp - null, timestamp или string
     * @param null $format
     * @return int
     */
    public static function prepareTimestamp($timestamp = null, $format = null)
    {
        if (!$timestamp) {
            return time();
        }

        if ($format) {
            $date = \DateTime::createFromFormat($format, $timestamp);

            return $date->getTimestamp();
        }

        if (!HDates::isTimestamp($timestamp)) {
            return strtotime($timestamp);
        }

        return $timestamp;
    }

    /**
     * Возвращает день недели
     * 1-пн ... 7-вс
     *
     * @param            $date
     *
     * @return string
     */
    public static function weekDay($date)
    {
        $days = self::getWeekDays();
        $day = date('N', strtotime($date));
        return $days[$day%7];
    }

    public static function period($period, $genetive = false)
    {
        $period = preg_replace_callback('/(\d)(d|m|y)/', function ($matches) use ($genetive) {
            switch ($matches[2]) {
                case 'd':
                    return $matches[1] . ' ' . HStrings::pluralForm($matches[1], $genetive ? 'дня' : 'день', $genetive ? 'дней' : 'дня', 'дней');
                case 'm':
                    return $matches[1] . ' ' . HStrings::pluralForm($matches[1], $genetive ? 'месяца' : 'месяц', 'месяцев', 'месяцев');
                case 'y':
                    return $matches[1] . ' ' . HStrings::pluralForm($matches[1], $genetive ? 'года' : 'год', $genetive ? 'лет' : 'года', 'лет');
                default:
                    return $matches[0];
            }
        }, $period);

        return $period;
    }

    public static function time($seconds)
    {
        return date("H:i:s", strtotime($seconds));
    }

    public static function timeToStr($seconds)
    {
        $d = (int)($seconds/self::SECONDS_1_DAY);
        $h = (int)($seconds/self::SECONDS_1_HOUR)%24;
        $m = (int)($seconds/self::SECONDS_1_MINUTE)%60;
        $s = $seconds%60;

        $str = "";

        $d && $str .= "$d ".\Yii::t('app', 'д')." ";
        $h && $str .= "$h ".\Yii::t('app', 'ч')." ";
        $m && $str .= "$m ".\Yii::t('app', 'м')." ";

        $str .= "$s ".\Yii::t('app', 'с');

        return $str;
    }

    public static function gmtTimeStamp($time){
        return gmdate("Y-m-d H:i:s", $time);
    }

    public static function toSec($time)
    {
        return strtotime($time) - strtotime('00:00:00');
    }

    public static function getTimeZone($time)
    {

        return strtotime($time) - strtotime('00:00:00');
    }

    public static function timeUi($seconds)
    {
        $units = array(
          "w" => 7 * 24 * 3600,
          "d" => 24 * 3600,
          "h" => 3600,
          "min" => 60,
          "sec" => 1,
        );

        // specifically handle zero
        if ($seconds == 0) {
            return "0 sec";
        }

        $s = "";

        foreach ($units as $name => $divisor) {
            if ($quot = intval($seconds / $divisor)) {
                $s .= "$quot $name";
                $s .= (abs($quot) > 1 ? "s" : "") . ", ";
                $seconds -= $quot * $divisor;
            }
        }

        return substr($s, 0, -2);
    }

    public static function diff($from, $to = null)
    {
        $to = $to ?: HDates::long();
        return strtotime($to) - strtotime($from);
    }

    public static function daysDiff($dateFrom, $to = null)
    {
        return round(HDates::diff($dateFrom, $to)/(60*60*24));
    }

    public static function smoothValue($dateFrom, $days, $fromValue, $toValue = 0)
    {
        $daysPast = HDates::diff($dateFrom, HDates::short())/(60*60*24);

        $daysPast = min($daysPast, $days);

        return round($fromValue + ($toValue - $fromValue) / $days * $daysPast);
    }

    /**
     * @param $date_start
     * @param $date_end
     * @param int $periodDays
     * @return \DateTime[]
     * @throws \Exception
     */
    public static function toArray($date_start, $date_end, $periodDays = 1)
    {
        $period = new \DatePeriod(
            new \DateTime($date_start),
            new \DateInterval("P{$periodDays}D"),
            new \DateTime($date_end)
        );
        /** @var \DateTime[] $dates */
        $dates = iterator_to_array($period);
        $dates[] = date_create($date_end);

        return $dates;
    }

    /**
     * Конвертирует дату в анл яз
     *
     */
    function date_rus2eng($text)
    {
        //краткаие месяцы
        $eng = array("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");
        $rus = array("Янв", "Фев", "Мар", "Апр", "Май", "Июн", "Июл", "Авг", "Сен", "Окт", "Ноя", "Дек");

        return str_replace(",", " ", str_replace($rus, $eng, $text));
    }

    /**
     * Конвертирует дату в рус яз
     *
     */
    public static function date_eng2rus($text, $short = true, $genitive = false, $inversely = false)
    {
        if ($short) {
            $eng = array("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");
            $rus = array("Янв", "Фев", "Мар", "Апр", "Май", "Июн", "Июл", "Авг", "Сен", "Окт", "Ноя", "Дек");

        } elseif ($genitive) {
            $eng = array(
                "January",
                "February",
                "March",
                "April",
                "May",
                "June",
                "July",
                "August",
                "September",
                "October",
                "November",
                "December",
            );
            $rus = array(
                "Января",
                "Февраля",
                "Марта",
                "Апреля",
                "Мая",
                "Июня",
                "Июля",
                "Августа",
                "Сентября",
                "Октября",
                "Ноября",
                "Декабря"
            );
        } else {
            $eng = array(
                "January",
                "February",
                "March",
                "April",
                "May",
                "June",
                "July",
                "August",
                "September",
                "October",
                "November",
                "December",
            );
            $rus = array(
                "Январь",
                "Февраль",
                "Март",
                "Апрель",
                "Май",
                "Июнь",
                "Июль",
                "Август",
                "Сентябрь",
                "Октябрь",
                "Ноябрь",
                "Декабрь"
            );
        }

        if ($inversely) {
            $rusTemp = $rus;
            $rus = $eng;
            $eng = $rusTemp;
        }

        return str_replace(",", " ", str_replace($eng, $rus, $text));
    }

    public static function parseMobileDate($datetime) {
        preg_match('/(\d{4}-\d{2}-\d{2})\s(\d{1,4}):(\d{2}):(\d{2})\s?(.*)/', $datetime, $matches);

        if (count($matches) != 6)
            return $datetime;

        $date = $matches[1];
        $hour = $matches[2];
        $min = $matches[3];
        $sec = $matches[4];
        $details = trim($matches[5]);

        $needNormalizeTo24 = false;
        if (!empty($details)) {
            if ($details == "после полудня" || $details == "PM" || $details == "p.m.")
                $needNormalizeTo24 = true;
        }

        $normalHour = intval($hour);
        switch (strlen($hour))
        {
            case 1:
                break;
            case 2:
                if ($normalHour > 23)
                    $normalHour = intval(substr($hour, 0, 1));
                break;
            case 3:
                $normalHour = intval(substr($hour, 2, 1));
                break;
            case 4:
                $normalHour = intval(substr($hour, 2, 2));
                break;
        }

        if ($needNormalizeTo24) {
            if ($normalHour < 12)
                $normalHour+=12;
        }

        $parseDate = HDates::long(strtotime($date) + $normalHour * 3600 + intval($min) * 60 + intval($sec) );

        return $parseDate;
    }

    public static function parseDate($datetime, $asArray = false) {
        // аналогично self::parseMobileDate

        preg_match('/(\d{4})-(\d{2})-(\d{2})\s(\d{1,4}):(\d{2}):(\d{2})\s?(.*)/', $datetime, $matches);

        if (count($matches) != 8)
            return $datetime;

        $year = $matches[1];
        $month = $matches[2];
        $day = $matches[3];
        $hour = $matches[4];
        $min = $matches[5];
        $sec = $matches[6];
        $details = trim($matches[7]);

        $needNormalizeTo24 = false;
        if (!empty($details)) {
            if ($details == "после полудня" || $details == "PM" || $details == "p.m.")
                $needNormalizeTo24 = true;
        }

        $normalHour = intval($hour);
        switch (strlen($hour))
        {
            case 1:
                break;
            case 2:
                if ($normalHour > 23)
                    $normalHour = intval(substr($hour, 0, 1));
                break;
            case 3:
                $normalHour = intval(substr($hour, 2, 1));
                break;
            case 4:
                $normalHour = intval(substr($hour, 2, 2));
                break;
        }

        if ($needNormalizeTo24) {
            if ($normalHour < 12)
                $normalHour+=12;
        }

        $parseDate = [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'hour' => $normalHour,
            'min' => $min,
            'sec' => $sec
        ];

        if (!$asArray) {
            return (object) $parseDate;
        }

        return $parseDate;
    }

    /**
     * @param string $date_1 YYYY-MM-DD HH:MM:SS
     * @param string $date_2 YYYY-MM-DD HH:MM:SS
     * @param string $differenceFormat
     * '%y Year %m Month %d Day %h Hours %i Minute %s Seconds' =>  1 Year 3 Month 14 Day 11 Hours 49 Minute 36 Seconds
     * '%y Year %m Month %d Day'                               =>  1 Year 3 Month 14 Days
     * '%m Month %d Day'                                       =>  3 Month 14 Day
     * '%d Day %h Hours'                                       =>  14 Day 11 Hours
     * '%d Day'                                                =>  14 Days
     * '%h Hours %i Minute %s Seconds'                         =>  11 Hours 49 Minute 36 Seconds
     * '%i Minute %s Seconds'                                  =>  49 Minute 36 Seconds
     * '%h Hours                                               =>  11 Hours
     * '%a Days                                                =>  468 Days
     * @return string
     */
    public static function dateDifference($date_1 , $date_2 , $differenceFormat = '%a' )
    {
        $datetime1 = date_create($date_1);
        $datetime2 = date_create($date_2);

        $interval = date_diff($datetime1, $datetime2);

        return $interval->format($differenceFormat);
    }

    public static function getMonths($short = true, $genetive = false)
    {
        return $short ? [
            \Yii::t('app', "Янв"),
            \Yii::t('app', "Фев"),
            \Yii::t('app', "Мар"),
            \Yii::t('app', "Апр"),
            \Yii::t('app', "Май"),
            \Yii::t('app', "Июн"),
            \Yii::t('app', "Июл"),
            \Yii::t('app', "Авг"),
            \Yii::t('app', "Сен"),
            \Yii::t('app', "Окт"),
            \Yii::t('app', "Ноя"),
            \Yii::t('app', "Дек"),
        ] : [
            $genetive ? \Yii::t('app', "Января")   : \Yii::t('app', "Январь"),
            $genetive ? \Yii::t('app', "Февраля")  : \Yii::t('app', "Февраль"),
            $genetive ? \Yii::t('app', "Марта")    : \Yii::t('app', "Март"),
            $genetive ? \Yii::t('app', "Апреля")   : \Yii::t('app', "Апрель"),
            $genetive ? \Yii::t('app', "Мая")      : \Yii::t('app', "Май"),
            $genetive ? \Yii::t('app', "Июня")     : \Yii::t('app', "Июнь"),
            $genetive ? \Yii::t('app', "Июля")     : \Yii::t('app', "Июль"),
            $genetive ? \Yii::t('app', "Августа")  : \Yii::t('app', "Август"),
            $genetive ? \Yii::t('app', "Сентября") : \Yii::t('app', "Сентябрь"),
            $genetive ? \Yii::t('app', "Октября")  : \Yii::t('app', "Октябрь"),
            $genetive ? \Yii::t('app', "Ноября")   : \Yii::t('app', "Ноябрь"),
            $genetive ? \Yii::t('app', "Декабря")  : \Yii::t('app', "Декабрь"),
        ];
    }

    public static function getMonth($number, $short = true, $genetive = false)
    {
        return ArrayHelper::getValue(self::getMonths($short, $genetive), (int)$number - 1);
    }

    public static function asMonth(string $date, bool $short = false, bool $comma = true): string
    {
        $date = explode('-', $date);
        return (
            HDates::getMonth($date[1], $short) .
            ($comma ? ', ' : ' ') .
            $date[0]
        );
    }

    public static function getWeekDays($short = true)
    {
        return $short ? [
            \Yii::t('app', 'Вс'),
            \Yii::t('app', 'Пн'),
            \Yii::t('app', 'Вт'),
            \Yii::t('app', 'Ср'),
            \Yii::t('app', 'Чт'),
            \Yii::t('app', 'Пт'),
            \Yii::t('app', 'Сб'),
        ] : [
            \Yii::t('app', 'Воскресенье'),
            \Yii::t('app', 'Понедельник'),
            \Yii::t('app', 'Вторник'),
            \Yii::t('app', 'Среда'),
            \Yii::t('app', 'Четверг'),
            \Yii::t('app', 'Пятница'),
            \Yii::t('app', 'Суббота'),
        ];
    }

    public static function getPresetDate($dateFrom, $dateTo = false)
    {
        $dateFrom = self::short($dateFrom);
        $dateTo  = $dateTo ? self::short($dateTo) : self::short();

        return "$dateFrom - $dateTo";
    }

    public static function getReconcileDate()
    {
        return self::getPresetDate(self::short('2016-01-01'), HDates::short());
    }

    public static function isIntersect($dateFrom1, $dateTo1, $dateFrom2, $dateTo2)
    {
        return !($dateFrom1 >= $dateTo2 || $dateTo1 <= $dateFrom2);
    }

    public static function inDatePeriod($date, $dateFrom, $dateTo)
    {
        $date = HDates::long($date);
        return HDates::long($dateFrom) <= $date && $date <= HDates::long($dateTo);
    }

    public static function dmy2ymd($date, $time = true, $timeDelimiter = ' ')
    {
        $parts = explode($timeDelimiter, $date);
        list($day, $month, $year) = explode('.', $parts[0]);
        return implode('-', [$year, $month, $day]) . ($time ? $timeDelimiter.$parts[1] : '');
    }

    public static function ymd2dmy($date, $time = true, $timeDelimiter = ' ')
    {
        $parts = explode($timeDelimiter, $date);
        list($year, $month, $day) = explode('-', $parts[0]);
        return implode('.', [$day, $month, $year]) . ($time ? $timeDelimiter.$parts[1] : '');
    }

    public static function apiContextFormat($dateTime, $time = true)
    {
        $timestamp = strtotime($dateTime);

        $timeParts = [
            date('n', $timestamp),
            self::getMonths()[date('m', $timestamp)],
            date('Y')
        ];

        if ($time) {
            $timeParts[] = '/';
            $timeParts[] = date('H:m', $timestamp);
        }

        return implode(' ', $timeParts);
    }

    public static function validateDate($date)
    {
        if (!strtotime($date)) return false;

        return preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $date);
    }

    /**
     * Проверка строки на валидную дату.
     *
     * @param $date string Строка вида 'Y-m-d'
     * @return bool
     */
    public static function strongValidateDate(string $date): bool
    {
        $pattern = '/(19|20)\d\d-((0[1-9]|1[012])-(0[1-9]|[12]\d)|(0[13-9]|1[012])-30|(0[13578]|1[02])-31)/';

        return (bool)(preg_match($pattern, $date));
    }

    /**
     * Метод преобразует срок действия карты из формата mm/yy в Y-m-d и возвращает его.
     * Если переданная дата имеет другой формат, возвращает null.
     *
     * @param string $date
     *
     * @return string|null
     */
    public static function getCardLastDate(string $date): ?string
    {
        $reg = '~^(0?[1-9]|1[012])[/]([0-9][0-9])$~';
        if (preg_match($reg, $date)) {
            return HDates::last(preg_replace($reg,'20$2-$1-01', $date));
        }
        return null;
    }

    /**
     * Валидация с возможностью
     * указать формат даты.
     *
     * @param $date
     * @param string $format
     * @return bool
     */
    public static function validateDateByFormat($date, string $format = 'Y-m-d H:i:s'): bool
    {
            $d = \DateTime::createFromFormat($format, $date);
            return $d && $d->format($format) == $date;
        }
}
