<?php

namespace common\helpers;

use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\User;
use common\models\user\UserTop;

/**
 * Формирование и прогрев кэша ответа API /v1/stats (server + tops).
 * Используется в API при cache miss и в console storage/update-tops для предзаполнения кэша.
 */
class StatsCacheHelper
{
    /**
     * Кэш консоли stats/active-players-cache и GET /v1/stats/global-records (глобальный ответ, в строках — by_server).
     * @see \console\controllers\StatsController::actionActivePlayersCache
     */
    public const CACHE_KEY_ACTIVE_PLAYERS_GLOBAL = 'statistics_active_players_global_v1';

    /** TTL записи в кэше {@see CACHE_KEY_ACTIVE_PLAYERS_GLOBAL}, секунды (48 часов). */
    public const ACTIVE_PLAYERS_GLOBAL_CACHE_TTL = 48 * 3600;

    /**
     * Порог суммарного playtime (мин.) для глобального кэша: SUM(playtime) по всем серверам, условие >=.
     * @see \console\controllers\StatsController::actionActivePlayersCache
     */
    public const ACTIVE_PLAYERS_MIN_PLAYTIME_MINUTES = 20 * 24 * 60;

    /**
     * Playtime только на конкретном сервере (мин.): для per-server кэша и среза by_server в глобальном ответе —
     * строго больше этого значения (т.е. попадание при playtime > 10 суток).
     * @see \console\controllers\StatsController::actionActivePlayersCache
     */
    public const ACTIVE_PLAYERS_PER_SERVER_PLAYTIME_MINUTES = 10 * 24 * 60;

    /**
     * Ключ кэша активных игроков по одному серверу (тот же формат строк, что и глобальный).
     * Заполняется той же консольной командой {@see \console\controllers\StatsController::actionActivePlayersCache}.
     */
    public static function cacheKeyActivePlayersServer(string $serverTag): string
    {
        $t = mb_strtolower(trim($serverTag), 'UTF-8');

        return 'statistics_active_players_server_v1_' . $t;
    }

    /** Ключ кэша: api_stats_v2_{serverTag}_{wipe} (v2 — с avatar_frame_url в топах) */
    public static function cacheKey(string $serverTag, ?string $wipe): string
    {
        return 'api_stats_v2_' . $serverTag . '_' . ($wipe ?? 'current');
    }

    /** TTL кэша в секундах (5 минут, как в API). */
    public const CACHE_TTL = 300;

    /** TTL таблицы «все игроки сервера» (GET /v1/stats/server-players-table), секунды. */
    public const SERVER_PLAYERS_TABLE_TTL = 900;

    /**
     * Кэш плоского списка игроков с метриками за вайп (сортировка на клиенте).
     */
    public static function cacheKeyServerPlayersTable(string $serverTag, string $wipe): string
    {
        $tag = preg_replace('/[^a-zA-Z0-9_-]/', '_', $serverTag);
        $h = md5($wipe);

        return 'api_stats_server_players_table_v1_' . $tag . '_' . $h;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, generated_at: int}
     */
    public static function buildServerPlayersTablePayload(Servers $server, string $wipe): array
    {
        $rows = Statistics::aggregatePlayerRowsForWipe($server, $wipe);
        if ($rows === []) {
            return ['items' => [], 'generated_at' => time()];
        }

        $steamIds = array_values(array_unique(array_map(static function ($r) {
            return (string) ($r['steam_id'] ?? '');
        }, $rows)));
        $steamIds = array_values(array_filter($steamIds, static function ($id) {
            return $id !== '';
        }));

        $users = $steamIds === [] ? [] : User::find()
            ->where(['steam_id' => $steamIds, 'is_stats' => 1])
            ->indexBy('steam_id')
            ->all();

        $items = [];
        foreach ($rows as $row) {
            $sid = (string) ($row['steam_id'] ?? '');
            if ($sid === '' || !isset($users[$sid])) {
                continue;
            }
            /** @var User $u */
            $u = $users[$sid];
            $kills = (int) Statistics::getParam($row, 'kills');
            $deaths = (int) Statistics::getParam($row, 'deaths');
            $kd = $deaths > 0 ? round($kills / $deaths, 3) : ($kills > 0 ? (float) $kills : 0.0);

            $items[] = [
                'steam_id' => $sid,
                'username' => (string) $u->username,
                'avatar' => (string) $u->getAvatar(),
                'kills' => $kills,
                'deaths' => $deaths,
                'kd' => $kd,
                'playtime' => (int) Statistics::getParam($row, 'playtime'),
                'scientists' => (int) Statistics::getParam($row, 'scientists'),
                'reider' => (float) ($row['reider'] ?? 0),
                'farmer' => (float) ($row['farmer'] ?? 0),
                'fishing' => (float) ($row['fishing'] ?? 0),
                'hunter' => (float) ($row['hunter'] ?? 0),
                'fermer' => (float) ($row['fermer'] ?? 0),
            ];
        }

        return ['items' => $items, 'generated_at' => time()];
    }

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
