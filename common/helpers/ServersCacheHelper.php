<?php

namespace common\helpers;

use Yii;
use common\models\servers\Servers;
use common\models\statistics\Statistics;

/**
 * Формирование и прогрев кэша ответа API /v1/servers (index).
 * Используется в API при cache miss и в console storage/update-servers для предзаполнения кэша.
 */
class ServersCacheHelper
{
    /** TTL кэша в секундах (публичный список серверов). */
    public const CACHE_TTL = ApiPublicCacheTtl::SECONDS;

    /**
     * Собрать payload для кэша списка серверов (servers + projectStats).
     *
     * @param string $language Язык (ru, en) для Yii::t('database', ...)
     * @return array{servers: array, projectStats: array}
     */
    public static function buildIndexPayload(string $language): array
    {
        $prevLanguage = Yii::$app->language;
        Yii::$app->language = $language;

        try {
            $servers = Servers::find()
                ->with(['serversTags', 'mapEntity', 'mapList'])
                ->where(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                ->orderBy(['sort' => SORT_ASC])
                ->all();

            $projectStats = Statistics::projectStats();

            $serversData = [];
            foreach ($servers as $server) {
                $serversData[] = self::formatServer($server, false);
            }

            return [
                'servers' => $serversData,
                'projectStats' => $projectStats,
            ];
        } finally {
            Yii::$app->language = $prevLanguage;
        }
    }

    /**
     * Форматирование данных сервера (как в ServersController::formatServer).
     *
     * @param Servers $server
     * @param bool $detailed
     * @return array
     */
    public static function formatServer(Servers $server, bool $detailed = false): array
    {
        $monitoring = $server->monitoring();

        $data = [
            'id' => $server->id,
            'tag' => $server->tag,
            'name' => Yii::t('database', $server->name ?: $server->monitoring_name ?: ''),
            'monitoring_name' => Yii::t('database', $server->monitoring_name ?: ''),
            'description' => Yii::t('database', $server->monitoring_description ?? ''),
            'monitoring_description' => Yii::t('database', $server->monitoring_description ?? ''),
            'status' => $server->status,
            'players' => (int) $server->players,
            'max' => (int) $server->max,
            'joined' => (int) ($server->joined ?? 0),
            'queued' => (int) ($server->queued ?? 0),
            'ip' => $server->ip,
            'text_ip' => $server->text_ip,
            'port' => (int) $server->port,
            'minMapSize' => (int) $server->min_map_size,
            'maxMapSize' => (int) $server->max_map_size,
            'nextWipe' => $server->next_wipe,
            'nextWipeTimestamp' => $server->next_wipe ? (($ts = strtotime($server->next_wipe)) !== false ? $ts : null) : null,
            'wipeType' => $server->wipeTypeText() ?? 'Вайп',
            'wipe_type' => (int) ($server->wipe_type ?? 0),
            'wipe_weekday' => (int) ($server->wipe_weekday ?? 5),
            'clans_enabled' => (bool) $server->getClansEnabledValue(),
            'current_wipe' => $server->wipe ?? null,
            'monitoring' => [
                'percentPlayers' => $monitoring['percentPlayers'] ?? 0,
                'percentJoined' => $monitoring['percentJoined'] ?? 0,
                'percentQueued' => $monitoring['percentQueued'] ?? 0,
                'percentPlayersAbsolute' => $monitoring['percentPlayersAbsolute'] ?? 0,
                'percentJoinedAbsolute' => $monitoring['percentJoinedAbsolute'] ?? 0,
                'percentQueuedAbsolute' => $monitoring['percentQueuedAbsolute'] ?? 0,
            ],
        ];

        if ($server->mapList) {
            $imagePath = $server->mapList->image ?? $server->mapList->image_preview ?? null;
            $data['map'] = [
                'id' => $server->mapList->id,
                'name' => $server->mapList->hash ?? $server->mapList->name ?? null,
                'size' => $server->mapList->size ?? $server->mapList->size_int ?? null,
                'seed' => $server->mapList->seed ?? null,
                'image' => self::getMapImageUrl($imagePath),
            ];
        } elseif ($server->mapEntity) {
            $data['map'] = [
                'id' => $server->mapEntity->id,
                'name' => $server->mapEntity->name,
                'size' => $server->mapEntity->size ?? null,
                'seed' => $server->mapEntity->seed ?? null,
                'image' => $server->mapEntity->image ?? null,
            ];
        }

        if ($server->serversTags) {
            $data['tags'] = [];
            foreach ($server->serversTags as $tag) {
                $data['tags'][] = [
                    'id' => $tag->id,
                    'name' => Yii::t('database', $tag->name ?: ''),
                    'title' => Yii::t('database', $tag->title ?: $tag->name ?: ''),
                    'link' => $tag->link,
                    'link_name' => $tag->link_name,
                    'color' => $tag->color,
                    'icon' => $tag->icon,
                ];
            }
        }

        if ($detailed) {
            $data['sort'] = $server->sort ?? null;
        }

        return $data;
    }

    /**
     * Публичный URL изображения карты (S3 или как есть).
     */
    public static function getMapImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $s3PublicUrl = Yii::$app->settings->get('s3_publicUrl');
        if (empty($s3PublicUrl)) {
            return $path;
        }
        $path = ltrim($path, '/');
        return rtrim($s3PublicUrl, '/') . '/' . $path;
    }
}
