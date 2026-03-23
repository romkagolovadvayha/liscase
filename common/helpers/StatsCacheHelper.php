<?php

namespace common\helpers;

use common\models\servers\Servers;
use common\models\user\UserTop;

/**
 * Формирование и прогрев кэша ответа API /v1/stats (server + tops).
 * Используется в API при cache miss и в console storage/update-tops для предзаполнения кэша.
 */
class StatsCacheHelper
{
    /** Ключ кэша: api_stats_{serverTag}_{wipe} */
    public static function cacheKey(string $serverTag, ?string $wipe): string
    {
        return 'api_stats_' . $serverTag . '_' . ($wipe ?? 'current');
    }

    /** TTL кэша в секундах (5 минут, как в API). */
    public const CACHE_TTL = 300;

    /**
     * Собрать payload для кэша: server + tops (без wipes, servers, userTops — они дополняются в API).
     *
     * @param Servers $server
     * @param string|null $wipe
     * @return array{server: array, tops: array}
     */
    public static function buildPayload(Servers $server, ?string $wipe = null): array
    {
        if ($wipe === null) {
            $wipe = $server->currentWipe();
        }

        $tops = self::getTopsFormatted($server, $wipe);

        return [
            'server' => [
                'tag' => $server->tag,
                'name' => $server->monitoring_name,
                'current_wipe' => $server->currentWipe(),
            ],
            'tops' => $tops,
        ];
    }

    /**
     * Топы в формате API (как в StatsController::getTops).
     */
    public static function getTopsFormatted(Servers $server, ?string $wipe = null): array
    {
        if ($wipe === null) {
            $wipe = $server->currentWipe();
        }

        $tops = UserTop::getUserTops($server, $wipe, false);

        $keyMapping = [
            'reider' => 'reider',
            'killer' => 'kills',
            'peaceful' => 'scientists',
            'playtime' => 'playtime',
            'farmer' => 'farmer',
            'fishing' => 'fishing',
            'hunter' => 'hunter',
            'fermer' => 'fermer',
        ];

        $formattedTops = [];
        foreach ($tops as $dbKey => $topCategory) {
            $apiKey = array_search($dbKey, $keyMapping);
            if ($apiKey === false) {
                $apiKey = $dbKey;
            }
            $formattedTops[$apiKey] = [
                'label' => $topCategory['label'] ?? ucfirst($apiKey),
                'items' => array_map(function ($item) {
                    $position = isset($item['position']) ? $item['position'] + 1 : 1;
                    return [
                        'position' => $position,
                        'color' => $item['color'] ?? 'bronze',
                        'amount' => $item['amount'] ?? 0,
                        'steam_id' => $item['steam_id'],
                        'score' => $item['score'],
                        'link' => $item['link'] ?? '',
                        'username' => $item['username'] ?? '',
                        'avatar' => $item['avatar'] ?? '',
                        'avatar_frame_url' => $item['avatar_frame_url'] ?? null,
                        'status' => $item['status'] ?? null,
                        'is_hidden' => $item['is_hidden'] ?? false,
                    ];
                }, $topCategory['items'] ?? []),
            ];
        }

        foreach ($keyMapping as $apiKey => $dbKey) {
            if (!isset($formattedTops[$apiKey])) {
                $formattedTops[$apiKey] = [
                    'label' => ucfirst($apiKey),
                    'items' => [],
                ];
            }
        }

        return $formattedTops;
    }
}
