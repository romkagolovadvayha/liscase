<?php

namespace common\components\helpers;

use Yii;

class CacheArrayHelper
{
    const CACHE_TTL = 86400; // Время жизни кэша — 24 часа

    /**
     * Добавляет значение в массив в кэше по ключу, если такого ещё нет
     */
    public static function addToCacheArray($key, $value)
    {
        $cache = Yii::$app->cache;
        $data = $cache->get($key) ?: [];

        // Не добавляем, если уже есть
        if (!in_array($value, $data, true)) {
            $data[] = $value;
            $cache->set($key, $data, self::CACHE_TTL);
        }
    }

    /**
     * Извлекает нужное количество элементов из массива и удаляет их
     */
    public static function popFromCacheArray($key, $count)
    {
        $cache = Yii::$app->cache;
        $data = $cache->get($key) ?: [];

        if (empty($data)) {
            return [];
        }

        // Отрезаем нужное количество элементов
        $extracted = array_splice($data, 0, $count);

        // Обновляем кэш с оставшимися элементами
        $cache->set($key, $data, self::CACHE_TTL);

        return $extracted;
    }

    public static function withLock($key, callable $callback, $lockTtl = 5)
    {
        $lockKey = $key . '_lock';
        $cache = Yii::$app->cache;

        // Пытаемся установить блокировку
        if ($cache->add($lockKey, 1, $lockTtl)) {
            try {
                return $callback();
            } finally {
                $cache->delete($lockKey);
            }
        } else {
            // Повторить позже, или выбросить исключение
            sleep(1);
            CacheArrayHelper::withLock($key, $callback, $lockTtl);
        }
    }

    public static function pushBackToCacheArray($key, array $items)
    {
        $cache = Yii::$app->cache;
        $data = $cache->get($key) ?: [];

        // Вернуть обратно в начало
        $data = array_merge($items, $data);
        $cache->set($key, $data, self::CACHE_TTL);
    }
}