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

    public static function getMinificationArray($text)
    {
        // Удаление экранированных спецсимволов
        $text = stripslashes($text);

        // Преобразование мнемоник
        $text = html_entity_decode($text);
        $text = htmlspecialchars_decode($text, ENT_QUOTES);

        // Удаление html тегов
        $text = strip_tags($text);

        // Все в нижний регистр
        $text = mb_strtolower($text);

        // Удаление лишних символов
        $text = str_ireplace('ё', 'е', $text);
        $text = mb_eregi_replace("[^a-zа-яй0-9 ]", ' ', $text);

        // Удаление двойных пробелов
        $text = mb_ereg_replace('[ ]+', ' ', $text);

        // Преобразование текста в массив
        $words = explode(' ', $text);

        // Удаление дубликатов
        $words = array_unique($words);

        // Удаление предлогов и союзов
        $array = array(
            'без',  'близ',  'в',     'во',     'вместо', 'вне',   'для',    'до',
            'за',   'и',     'из',    'изо',    'из',     'за',    'под',    'к',
            'ко',   'кроме', 'между', 'на',     'над',    'о',     'об',     'обо',
            'от',   'ото',   'перед', 'передо', 'пред',   'предо', 'по',     'под',
            'подо', 'при',   'про',   'ради',   'с',      'со',    'сквозь', 'среди',
            'у',    'через', 'но',    'или',    'по'
        );

        $words = array_diff($words, $array);

        // Удаление пустых значений в массиве
        $words = array_diff($words, array(''));

        return $words;
    }
}