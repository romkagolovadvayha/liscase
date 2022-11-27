<?php

namespace common\components\helpers;

class ArrayValIterator
{
    /**
     * @param array $array
     * @param int   $currKey
     *
     * @return int|mixed
     */
    public static function getNextVal(&$array, $currKey)
    {
        $next = 0;
        reset($array);

        do {
            $tmpKey = current($array);
            $res    = next($array);
        } while (($tmpKey != $currKey) && $res);

        if ($res) {
            $next = current($array);
        }

        return $next;
    }

    /**
     * @param array $array
     * @param int   $currKey
     *
     * @return int|mixed
     */
    public static function getPrevVal(&$array, $currKey)
    {
        end($array);
        $prev = current($array);

        do {
            $tmpKey = current($array);
            $res    = prev($array);
        } while (($tmpKey != $currKey) && $res);

        if ($res) {
            $prev = current($array);
        }

        return $prev != end($array) ? $prev : null;
    }
}