<?php

namespace api\controllers\v1;

use Yii;
use common\helpers\ApiPublicCacheTtl;
use common\models\map\MapList;
use common\models\map\MapListVote;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\user\User;
use common\helpers\MapLocalization;
use api\components\jwt\JwtAuthFilter;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с картами и голосованием
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="Maps")
 */
class MapsController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // JWT авторизация требуется для голосования
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'only' => ['vote', 'index', 'detail'],
            'throwException' => false,
        ];

        return $behaviors;
    }

    /**
     * Получение списка карт для сервера с подробным описанием и списком проголосовавших
     * 
     * @OA\Get(
     *     path="/v1/maps",
     *     operationId="getMaps",
     *     tags={"Maps"},
     *     summary="Получить список карт для сервера",
     *     description="Возвращает список карт для указанного сервера с подробным описанием и списком проголосовавших",
     *     @OA\Parameter(
     *         name="server_id",
     *         in="query",
     *         description="ID сервера",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="server_tag",
     *         in="query",
     *         description="Тег сервера (альтернатива server_id)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список карт",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=404, description="Сервер не найден")
     * )
     */
    public function actionIndex()
    {
        $request = Yii::$app->request;
        $serverId = $request->get('server_id');
        $serverTag = $request->get('server_tag');

        // Получаем сервер с загрузкой связей
        $server = null;
        if ($serverId) {
            $server = Servers::find()
                ->with(['serversTags', 'mapList'])
                ->where(['id' => $serverId])
                ->one();
        } elseif ($serverTag) {
            $server = Servers::find()
                ->with(['serversTags', 'mapList'])
                ->where(['tag' => $serverTag])
                ->one();
        } else {
            // Если не указан, берем первый активный сервер
            $server = Servers::find()
                ->with(['serversTags', 'mapList'])
                ->andWhere(['status' => Servers::STATUS_ACTIVE])
                ->andWhere(['secret_map' => 0])
                ->orderBy(['sort' => SORT_ASC])
                ->one();
        }

        if (!$server) {
            return $this->errorResponse('SERVER_NOT_FOUND', 'Сервер не найден', [], 404);
        }

        $language = Yii::$app->language;
        $cache = Yii::$app->cache;
        $listCacheKey = 'api_maps_list_v2_' . $server->tag . '_' . $language;
        $cachedList = $cache->get($listCacheKey);
        if ($cachedList !== false && is_array($cachedList)) {
            $userVotedMapIds = [];
            $currentUser = Yii::$app->user->identity;
            if ($currentUser) {
                $userVotedMapIds = MapListVote::find()
                    ->where(['server_id' => $server->id, 'user_id' => $currentUser->id])
                    ->select('map_list_id')
                    ->column();
            }
            $maps = $cachedList['maps'];
            foreach ($maps as &$m) {
                $m['isVoted'] = in_array($m['id'], $userVotedMapIds);
            }
            unset($m);
            $this->setReadCacheHeaders($currentUser);
            return $this->successResponse([
                'maps' => $maps,
                'server' => $cachedList['server'],
                'totalMaps' => $cachedList['totalMaps'],
                'totalVotes' => $cachedList['totalVotes'],
                'maxVotes' => $cachedList['maxVotes'],
            ]);
        }

        // Получаем ID карт, которые уже зафиксированы на любом из серверов (кэшируем на 5 минут)
        $cacheKey = 'api_maps_fixed_ids';
        $cache = Yii::$app->cache;
        $fixedMapIds = $cache->get($cacheKey);
        
        if ($fixedMapIds === false) {
            $fixedMapIds = Servers::find()
                ->select('map_list_id')
                ->andWhere(['IS NOT', 'map_list_id', null])
                ->column();
            $cache->set($cacheKey, $fixedMapIds, ApiPublicCacheTtl::SECONDS);
        }

        // Вычисляем дату через 3 суток от текущего момента (следующий вайп — из календаря, иначе колонка сервера)
        $threeDaysFromNow = new \DateTime();
        $threeDaysFromNow->modify('+3 days');
        $nextWipeForWindow = $server->getFactNextWipe() ?? $server->next_wipe;
        $shouldShowUnfixedMaps = false;
        if ($nextWipeForWindow) {
            try {
                $serverNextWipe = new \DateTime($nextWipeForWindow);
                $shouldShowUnfixedMaps = $serverNextWipe <= $threeDaysFromNow;
            } catch (\Throwable $e) {
                $shouldShowUnfixedMaps = false;
            }
        }

        $mapQuery = MapList::find()
            ->alias('ml')
            ->andWhere(['IS NOT', 'ml.size_int', null])
            ->andWhere(['>=', 'ml.size_int', (int)$server->min_map_size])
            ->andWhere(['<=', 'ml.size_int', (int)$server->max_map_size])
            ->orderBy(['ml.created_at' => SORT_DESC]);
        
        // Исключаем все зафиксированные карты
        if (!empty($fixedMapIds)) {
            $mapQuery->andWhere(['NOT IN', 'ml.id', $fixedMapIds]);
        }
        
        // Не зафиксированные карты показываем только если следующий вайп (календарь/колонка) <= now + 3 дня
        if (!$shouldShowUnfixedMaps) {
            $mapQuery->andWhere(['=', 'ml.id', 0]); // Несуществующий ID, чтобы вернуть пустой результат
        }

        $maps = $mapQuery->all();
        if (empty($maps)) {
            $maps = [];
        }

        $mapIds = array_map(function($map) {
            return $map->id;
        }, $maps);

        // Получаем количество голосов для каждой карты
        $voteCounts = [];
        $userVotedMapIds = [];
        $maxVotes = 0;
        $totalVotes = 0;

        // Проверяем, авторизован ли пользователь
        $currentUser = Yii::$app->user->identity;

        if (!empty($mapIds)) {
            $rawCounts = MapListVote::find()
                ->select(['map_list_id', 'cnt' => 'COUNT(*)'])
                ->andWhere(['map_list_id' => $mapIds, 'server_id' => $server->id])
                ->groupBy('map_list_id')
                ->asArray()
                ->all();
            
            foreach ($rawCounts as $row) {
                $voteCounts[(int)$row['map_list_id']] = (int)$row['cnt'];
                $totalVotes += (int)$row['cnt'];
                if ($voteCounts[(int)$row['map_list_id']] > $maxVotes) {
                    $maxVotes = $voteCounts[(int)$row['map_list_id']];
                }
            }

            if ($currentUser) {
                $userVotedMapIds = array_map('intval', MapListVote::find()
                    ->select('map_list_id')
                    ->where([
                        'map_list_id' => $mapIds,
                        'server_id' => $server->id,
                        'user_id' => $currentUser->id,
                    ])
                    ->column());
            }
        }

        // Сортируем карты по количеству голосов
        if (!empty($maps)) {
            usort($maps, static function (MapList $a, MapList $b) use ($voteCounts) {
                $aVotes = $voteCounts[$a->id] ?? 0;
                $bVotes = $voteCounts[$b->id] ?? 0;

                if ($aVotes === $bVotes) {
                    $aTime = strtotime($a->created_at ?? 'now');
                    $bTime = strtotime($b->created_at ?? 'now');
                    return $bTime <=> $aTime;
                }

                return $bVotes <=> $aVotes;
            });
        }

        // Формируем данные карт
        $mapsData = [];
        $language = Yii::$app->language;
        $biomeLabels = MapLocalization::biomeLabels($language);

        foreach ($maps as $map) {
            $details = $map->data_json ? json_decode($map->data_json, true) : [];

            $mapsData[] = [
                'id' => (int)$map->id,
                'hash' => $map->hash,
                'type' => $map->map_type,
                'seed' => $map->seed,
                'size' => $map->size_int,
                'saveVersion' => $map->save_version,
                'downloadUrl' => $map->url,
                'rustMapsUrl' => $map->hash ? 'https://rustmaps.com/map/' . $map->hash : null,
                'image' => $this->getMapImageUrl($map->image ?: ($details['imageUrl'] ?? $map->image_url)),
                'imagePreview' => $this->getMapImageUrl($map->image_preview ?: ($details['thumbnailUrl'] ?? $map->thumbnail_url)),
                'rawImageUrl' => $this->getMapImageUrl($map->raw_image_url ?: ($details['rawImageUrl'] ?? null)),
                'imageIconUrl' => $this->getMapImageUrl($map->image_icon_url ?: ($details['imageIconUrl'] ?? null)),
                'isStaging' => (bool)$map->is_staging,
                'isCustomMap' => (bool)$map->is_custom_map,
                'canDownload' => (bool)$map->can_download,
                'totalMonuments' => $map->total_monuments,
                // Тяжёлые координаты загружаются через GET /maps/{id}
                // только при открытии detail modal.
                'monuments' => [],
                'landPercentage' => $map->land_percentage,
                'biomePercentages' => $details['biomePercentages'] ?? json_decode($map->biome_percentages_json ?? '[]', true),
                'biomeLabels' => $biomeLabels,
                'islands' => $map->islands,
                'mountains' => $map->mountains,
                'iceLakes' => $map->ice_lakes,
                'rivers' => $map->rivers,
                'lakes' => $map->lakes,
                'canyons' => $map->canyons,
                'oases' => $map->oases,
                'buildableRocks' => $map->buildable_rocks,
                'createdAt' => $map->created_at,
                'voteCount' => $voteCounts[$map->id] ?? 0,
                // Список пользователей также относится к detail endpoint.
                'voters' => [],
                'isVoted' => in_array($map->id, $userVotedMapIds),
            ];
        }

        // Формируем подробную информацию о сервере
        $monitoring = $server->monitoring();
        
        $serverData = [
            'id' => $server->id,
            'tag' => $server->tag,
            'name' => Yii::t('database', $server->name ?: $server->monitoring_name ?: ''),
            'monitoringName' => $server->monitoring_name,
            'description' => $server->monitoring_description ?? '',
            'status' => $server->status,
            'players' => (int)$server->players,
            'max' => (int)$server->max,
            'joined' => (int)($server->joined ?? 0),
            'queued' => (int)($server->queued ?? 0),
            'ip' => $server->ip,
            'port' => (int)$server->port,
            'minMapSize' => (int) ($server->min_map_size ?? 0),
            'maxMapSize' => (int) ($server->max_map_size ?? 0),
            'nextWipe' => ($nextWipeOut = $server->getFactNextWipe() ?? $server->next_wipe),
            'nextWipeTimestamp' => $nextWipeOut ? (($timestamp = strtotime($nextWipeOut)) !== false ? $timestamp : null) : null,
            'wipeType' => $server->wipeTypeText() ?? 'Вайп',
            'currentWipe' => $server->getFactWipe() ?? $server->wipe ?? null,
            'globalWipe' => $server->getFactGlobalWipe() ?? $server->global_wipe ?? null,
            'monitoring' => [
                'percentPlayers' => $monitoring['percentPlayers'] ?? 0,
                'percentJoined' => $monitoring['percentJoined'] ?? 0,
                'percentQueued' => $monitoring['percentQueued'] ?? 0,
                'percentPlayersAbsolute' => $monitoring['percentPlayersAbsolute'] ?? 0,
                'percentJoinedAbsolute' => $monitoring['percentJoinedAbsolute'] ?? 0,
                'percentQueuedAbsolute' => $monitoring['percentQueuedAbsolute'] ?? 0,
            ],
        ];

        // Добавляем информацию о текущей карте (картинка и данные как в старой версии)
        if ($server->map_list_id) {
            $fixedMap = MapList::findOne($server->map_list_id);
            if ($fixedMap) {
                $imagePath = $fixedMap->image_preview ?? $fixedMap->image ?? null;
                $serverData['currentMap'] = [
                    'id' => $fixedMap->id,
                    'hash' => $fixedMap->hash,
                    'size' => $fixedMap->size_int ?? $fixedMap->size,
                    'seed' => $fixedMap->seed,
                    'image' => $this->getMapImageUrl($imagePath),
                ];
            }
        }

        // Добавляем теги сервера
        if ($server->serversTags) {
            $serverData['tags'] = [];
            foreach ($server->serversTags as $tag) {
                $serverData['tags'][] = [
                    'id' => $tag->id,
                    'name' => Yii::t('database', $tag->name ?: ''),
                    'link' => $tag->link,
                ];
            }
        }

        $response = [
            'maps' => $mapsData,
            'server' => $serverData,
            'totalMaps' => count($mapsData),
            'totalVotes' => $totalVotes,
            'maxVotes' => $maxVotes,
        ];
        $toCache = [
            'maps' => array_map(function ($m) {
                $m['isVoted'] = false;
                return $m;
            }, $mapsData),
            'server' => $serverData,
            'totalMaps' => count($mapsData),
            'totalVotes' => $totalVotes,
            'maxVotes' => $maxVotes,
        ];
        $cache->set($listCacheKey, $toCache, ApiPublicCacheTtl::SECONDS);

        $this->setReadCacheHeaders($currentUser);
        return $this->successResponse($response);
    }

    /**
     * Голосование за карту
     * 
     * @OA\Post(
     *     path="/v1/maps/vote",
     *     operationId="voteForMap",
     *     tags={"Maps"},
     *     summary="Проголосовать за карту",
     *     description="Ставит или снимает голос за карту. Требует авторизации и минимум 1 час игры на сервере.",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"map_id", "server_id"},
     *                 @OA\Property(property="map_id", type="integer", description="ID карты"),
     *                 @OA\Property(property="server_id", type="integer", description="ID сервера")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Голос обработан",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Ошибка валидации"),
     *     @OA\Response(response=401, description="Требуется авторизация"),
     *     @OA\Response(response=404, description="Карта или сервер не найдены")
     * )
     */
    public function actionVote()
    {
        $user = $this->getCurrentUser();

        $request = Yii::$app->request;
        $mapId = (int)$request->post('map_id');
        $serverId = (int)$request->post('server_id');

        if (!$mapId) {
            return $this->errorResponse('VALIDATION_ERROR', 'map_id обязателен', ['map_id' => 'ID карты не может быть пустым'], 400);
        }

        if (!$serverId) {
            return $this->errorResponse('VALIDATION_ERROR', 'server_id обязателен', ['server_id' => 'ID сервера не может быть пустым'], 400);
        }

        $map = MapList::findOne($mapId);
        if (!$map) {
            return $this->errorResponse('MAP_NOT_FOUND', 'Карта не найдена', [], 404);
        }

        $server = Servers::findOne($serverId);
        if (!$server) {
            return $this->errorResponse('SERVER_NOT_FOUND', 'Сервер не найден', [], 404);
        }

        // Проверяем, есть ли уже голос за эту карту
        $existingVote = MapListVote::find()
            ->where([
                'map_list_id' => $map->id,
                'server_id' => $server->id,
                'user_id' => $user->id,
            ])
            ->one();

        $voteAdded = false;
        $voteRemoved = false;

        if ($existingVote) {
            // Удаляем голос (отмена)
            if ($existingVote->delete()) {
                $voteRemoved = true;
            } else {
                return $this->errorResponse('DELETE_ERROR', 'Ошибка при удалении голоса', [], 500);
            }
        } else {
            // Проверяем playtime только при добавлении голоса
            $playtime = Statistics::find()
                ->andWhere(['steam_id' => $user->steam_id])
                ->andWhere(['key' => 'playtime'])
                ->sum('value');

            if ((int)$playtime < 60) {
                return $this->errorResponse('INSUFFICIENT_PLAYTIME', 'Чтобы проголосовать, нужно отыграть на сервере минимум 1 час', [
                    'required' => 60,
                    'current' => (int)$playtime,
                ], 400);
            }

            // Добавляем голос
            $vote = new MapListVote([
                'map_list_id' => $map->id,
                'server_id' => $server->id,
                'user_id' => $user->id,
            ]);

            if ($vote->save()) {
                $voteAdded = true;
            } else {
                return $this->errorResponse('SAVE_ERROR', 'Ошибка при сохранении голоса', $vote->errors, 500);
            }
        }

        // Получаем обновленное количество голосов
        $voteCount = MapListVote::find()
            ->where(['map_list_id' => $map->id, 'server_id' => $server->id])
            ->count();

        // Получаем список проголосовавших (последние 50)
        $votes = MapListVote::find()
            ->where(['map_list_id' => $map->id, 'server_id' => $server->id])
            ->with('user')
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(50)
            ->all();

        $voters = [];
        foreach ($votes as $vote) {
            if ($vote->user) {
                $voters[] = [
                    'id' => $vote->user->id,
                    'username' => $vote->user->username,
                    'steamId' => $vote->user->steam_id,
                    'avatar' => $vote->user->getAvatar(),
                    'createdAt' => $vote->created_at,
                ];
            }
        }

        foreach (['ru-RU', 'en-US', 'ru', 'en'] as $language) {
            Yii::$app->cache->delete('api_maps_list_v2_' . $server->tag . '_' . $language);
        }

        return $this->successResponse([
            'isVoted' => $voteAdded,
            'voteCount' => $voteCount,
            'voters' => $voters,
            'message' => $voteAdded 
                ? 'Ваш голос успешно учтен!' 
                : 'Ваш голос снят!',
        ]);
    }

    /**
     * Получение детальной информации о карте
     * 
     * @OA\Get(
     *     path="/v1/maps/{id}",
     *     operationId="getMapDetail",
     *     tags={"Maps"},
     *     summary="Получить детальную информацию о карте",
     *     description="Возвращает подробную информацию о конкретной карте",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID карты",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="server_id",
     *         in="query",
     *         required=false,
     *         description="ID сервера (для получения информации о голосах)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Детальная информация о карте",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=404, description="Карта не найдена")
     * )
     */
    public function actionDetail($id)
    {
        $map = MapList::findOne($id);
        if (!$map) {
            return $this->errorResponse('MAP_NOT_FOUND', 'Карта не найдена', [], 404);
        }

        $request = Yii::$app->request;
        $serverId = $request->get('server_id');
        $serverIdKey = $serverId ? (int) $serverId : 0;
        $language = Yii::$app->language;
        $cacheKey = 'api_maps_detail_' . $id . '_' . $serverIdKey . '_' . $language;
        $cache = Yii::$app->cache;
        $currentUser = Yii::$app->user->identity;
        $cached = $cache->get($cacheKey);
        if ($cached !== false && is_array($cached)) {
            if ($currentUser && $serverIdKey) {
                $server = Servers::findOne($serverIdKey);
                if ($server) {
                    $userVote = MapListVote::find()
                        ->where(['map_list_id' => $map->id, 'server_id' => $server->id, 'user_id' => $currentUser->id])
                        ->exists();
                    $cached['userVotedMapIds'] = $userVote ? [(int) $map->id] : [];
                }
            }
            $this->setReadCacheHeaders($currentUser);
            return $this->successResponse($cached);
        }

        $server = null;
        if ($serverId) {
            $server = Servers::findOne($serverId);
        }

        $details = $map->data_json ? json_decode($map->data_json, true) : [];
        
        $monumentsRaw = $details['monuments'] ?? json_decode($map->monuments_json ?? '[]', true);
        if (!is_array($monumentsRaw)) {
            $monumentsRaw = [];
        }

        $monuments = [];
        foreach ($monumentsRaw as $monument) {
            $type = $monument['type'] ?? '';
            $monuments[] = [
                'type' => $type,
                'label' => MapLocalization::monument($type, Yii::$app->language),
                'coordinates' => $monument['coordinates'] ?? null,
            ];
        }

        $voteCount = 0;
        $voters = [];
        $userVotedMapIds = [];

        if ($server) {
            $voteCount = MapListVote::find()
                ->where(['map_list_id' => $map->id, 'server_id' => $server->id])
                ->count();

            $votes = MapListVote::find()
                ->where(['map_list_id' => $map->id, 'server_id' => $server->id])
                ->with('user')
                ->orderBy(['created_at' => SORT_DESC])
                ->limit(50)
                ->all();

            foreach ($votes as $vote) {
                if ($vote->user) {
                    $voters[] = [
                        'id' => $vote->user->id,
                        'username' => $vote->user->username,
                        'steamId' => $vote->user->steam_id,
                        'avatar' => $vote->user->getAvatar(),
                        'createdAt' => $vote->created_at,
                    ];
                }
            }

            // Проверяем, проголосовал ли текущий пользователь
            if ($currentUser) {
                $userVote = MapListVote::find()
                    ->where([
                        'map_list_id' => $map->id,
                        'server_id' => $server->id,
                        'user_id' => $currentUser->id,
                    ])
                    ->exists();
                if ($userVote) {
                    $userVotedMapIds[] = $map->id;
                }
            }
        }

        $language = Yii::$app->language;
        $biomeLabels = MapLocalization::biomeLabels($language);

        $mapData = [
            'id' => (int)$map->id,
            'hash' => $map->hash,
            'type' => $map->map_type,
            'seed' => $map->seed,
            'size' => $map->size_int,
            'saveVersion' => $map->save_version,
            'downloadUrl' => $map->url,
            'rustMapsUrl' => $map->hash ? 'https://rustmaps.com/map/' . $map->hash : null,
            'image' => $this->getMapImageUrl($map->image ?: ($details['imageUrl'] ?? $map->image_url)),
            'imagePreview' => $this->getMapImageUrl($map->image_preview ?: ($details['thumbnailUrl'] ?? $map->thumbnail_url)),
            'rawImageUrl' => $this->getMapImageUrl($map->raw_image_url ?: ($details['rawImageUrl'] ?? null)),
            'imageIconUrl' => $this->getMapImageUrl($map->image_icon_url ?: ($details['imageIconUrl'] ?? null)),
            'isStaging' => (bool)$map->is_staging,
            'isCustomMap' => (bool)$map->is_custom_map,
            'canDownload' => (bool)$map->can_download,
            'totalMonuments' => $map->total_monuments,
            'monuments' => $monuments,
            'landPercentage' => $map->land_percentage,
            'biomePercentages' => $details['biomePercentages'] ?? json_decode($map->biome_percentages_json ?? '[]', true),
            'biomeLabels' => $biomeLabels,
            'islands' => $map->islands,
            'mountains' => $map->mountains,
            'iceLakes' => $map->ice_lakes,
            'rivers' => $map->rivers,
            'lakes' => $map->lakes,
            'canyons' => $map->canyons,
            'oases' => $map->oases,
            'buildableRocks' => $map->buildable_rocks,
            'createdAt' => $map->created_at,
            'voteCount' => $voteCount,
            'voters' => $voters,
            'userVotedMapIds' => $userVotedMapIds,
        ];

        $toCache = $mapData;
        $toCache['userVotedMapIds'] = [];
        $cache->set($cacheKey, $toCache, ApiPublicCacheTtl::SECONDS);

        $this->setReadCacheHeaders($currentUser);
        return $this->successResponse($mapData);
    }

    private function setReadCacheHeaders(?User $currentUser): void
    {
        if ($currentUser) {
            Yii::$app->response->headers->set('Cache-Control', 'private, no-store');
            Yii::$app->response->headers->set('Vary', 'Origin, Authorization, Accept-Language');
            return;
        }

        Yii::$app->response->headers->set(
            'Cache-Control',
            'public, max-age=60, s-maxage=300, stale-while-revalidate=300'
        );
        Yii::$app->response->headers->set('Vary', 'Origin, Accept-Language');
    }

    /**
     * Получение URL изображения карты
     * 
     * @param string|null $path Путь к изображению
     * @return string|null
     */
    private function getMapImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        
        // Если это уже полный URL (http:// или https://), возвращаем как есть
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        
        // Добавляем S3 публичный URL
        $s3PublicUrl = Yii::$app->settings->get('s3_publicUrl');
        if (empty($s3PublicUrl)) {
            return $path;
        }
        
        // Убираем слэш в начале пути, если есть
        $path = ltrim($path, '/');
        
        // Объединяем URL
        return rtrim($s3PublicUrl, '/') . '/' . $path;
    }
}

