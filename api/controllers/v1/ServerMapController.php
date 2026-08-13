<?php

namespace api\controllers\v1;

use api\components\jwt\JwtAuthFilter;
use Yii;

/**
 * Authenticated, failure-tolerant bridge between the site and Rust Admin.
 */
class ServerMapController extends BaseApiController
{
    private const CACHE_TTL = 30;

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'except' => ['options'],
        ];

        return $behaviors;
    }

    /**
     * GET /v1/user/server-map
     */
    public function actionIndex()
    {
        $user = $this->getCurrentUser();
        $server = $user->getCurrentServer();
        Yii::$app->response->headers->set(
            'Cache-Control',
            'private, max-age=15, stale-while-revalidate=30'
        );

        if ($server === null) {
            return $this->successResponse($this->unavailable('server_not_found'));
        }

        $steamId = (string)$user->steam_id;
        $cacheKey = [
            'v1-user-server-map',
            (int)$server->id,
            $steamId,
        ];

        try {
            $remoteResult = Yii::$app->cache->getOrSet(
                $cacheKey,
                static function () use ($server, $steamId): array {
                    try {
                        return [
                            'success' => true,
                            'data' => Yii::$app->rustAdmin->serverMap(
                                (string)$server->ip,
                                (int)$server->port,
                                $steamId
                            ),
                        ];
                    } catch (\Throwable $throwable) {
                        Yii::warning(
                            'Rust Admin server map unavailable: ' . $throwable->getMessage(),
                            __METHOD__
                        );
                        return [
                            'success' => false,
                            'data' => null,
                        ];
                    }
                },
                self::CACHE_TTL
            );
        } catch (\Throwable $throwable) {
            Yii::warning(
                'Server map cache failed: ' . $throwable->getMessage(),
                __METHOD__
            );

            return $this->successResponse(
                $this->unavailable('rust_admin_unavailable', $server)
            );
        }

        if (
            empty($remoteResult['success'])
            || empty($remoteResult['data'])
            || !is_array($remoteResult['data'])
        ) {
            return $this->successResponse(
                $this->unavailable('rust_admin_unavailable', $server)
            );
        }
        $remote = $remoteResult['data'];

        $map = !empty($remote['map']) && is_array($remote['map'])
            ? $remote['map']
            : null;
        $players = !empty($remote['players']) && is_array($remote['players'])
            ? array_values($remote['players'])
            : [];

        return $this->successResponse([
            'available' => $map !== null,
            'reason' => $map === null ? 'map_not_ready' : null,
            'server' => $this->serverData($server),
            'map' => $map,
            'players' => $players,
        ]);
    }

    private function unavailable(string $reason, $server = null): array
    {
        return [
            'available' => false,
            'reason' => $reason,
            'server' => $server !== null ? $this->serverData($server) : null,
            'map' => null,
            'players' => [],
        ];
    }

    private function serverData($server): array
    {
        return [
            'id' => (int)$server->id,
            'tag' => (string)$server->tag,
            'name' => Yii::t(
                'database',
                $server->name ?: $server->monitoring_name ?: $server->tag
            ),
            'monitoringName' => (string)$server->monitoring_name,
            'ip' => (string)$server->ip,
            'port' => (int)$server->port,
        ];
    }
}
