<?php

namespace common\components\wipe_calendar;

use common\models\wipe_calendar\WipeCalendarEvent;
use Yii;

/**
 * Единый снимок вайпов из {@see WipeCalendarEvent}: один SELECT, результат в кэше 30 с.
 * Паттерн «одиночка» на уровне процесса — повторные вызовы в том же запросе читают Yii::$app->cache.
 */
final class WipeCalendarFactsProvider
{
    private const CACHE_KEY = 'wipe_calendar_facts_snapshot_v1';

    /** Кэш обращения к БД (секунды). */
    public const CACHE_TTL_SECONDS = 30;

    /**
     * @return array<int, array{fact_wipe: ?string, fact_next_wipe: ?string, fact_global_wipe: ?string}>
     */
    public static function getSnapshot(): array
    {
        $cache = Yii::$app->cache;
        $cached = $cache->get(self::CACHE_KEY);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $rows = WipeCalendarEvent::find()
            ->select(['server_id', 'event_type', 'event_at'])
            ->where([
                'event_type' => [
                    WipeCalendarEvent::TYPE_MAP_WIPE,
                    WipeCalendarEvent::TYPE_GLOBAL_WIPE,
                ],
            ])
            ->andWhere(['not', ['server_id' => null]])
            ->orderBy(['event_at' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray()
            ->all();

        $byServer = [];
        foreach ($rows as $row) {
            $sid = (int) ($row['server_id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $rawAt = (string) ($row['event_at'] ?? '');
            if ($rawAt === '') {
                continue;
            }
            $ts = strtotime($rawAt);
            if ($ts === false) {
                continue;
            }
            if (!isset($byServer[$sid])) {
                $byServer[$sid] = [];
            }
            $byServer[$sid][] = [
                't' => $ts,
                'type' => (string) $row['event_type'],
                's' => date('Y-m-d H:i:s', $ts),
            ];
        }

        $now = time();
        $result = [];
        foreach ($byServer as $sid => $events) {
            $factWipe = null;
            $factNext = null;
            $factGlobal = null;

            foreach ($events as $e) {
                if ($e['t'] <= $now) {
                    $factWipe = $e['s'];
                }
            }
            foreach ($events as $e) {
                if ($e['t'] > $now) {
                    $factNext = $e['s'];
                    break;
                }
            }
            foreach ($events as $e) {
                if ($e['type'] === WipeCalendarEvent::TYPE_GLOBAL_WIPE && $e['t'] > $now) {
                    $factGlobal = $e['s'];
                    break;
                }
            }

            $result[(int) $sid] = [
                'fact_wipe' => $factWipe,
                'fact_next_wipe' => $factNext,
                'fact_global_wipe' => $factGlobal,
            ];
        }

        $cache->set(self::CACHE_KEY, $result, self::CACHE_TTL_SECONDS);

        return $result;
    }

    /**
     * @return array{fact_wipe: ?string, fact_next_wipe: ?string, fact_global_wipe: ?string}
     */
    public static function getForServerId(int $serverId): array
    {
        if ($serverId <= 0) {
            return self::emptyFacts();
        }
        $snap = self::getSnapshot();

        return $snap[$serverId] ?? self::emptyFacts();
    }

    /**
     * @return array{fact_wipe: ?string, fact_next_wipe: ?string, fact_global_wipe: ?string}
     */
    private static function emptyFacts(): array
    {
        return [
            'fact_wipe' => null,
            'fact_next_wipe' => null,
            'fact_global_wipe' => null,
        ];
    }
}
