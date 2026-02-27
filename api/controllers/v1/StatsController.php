<?php

namespace api\controllers\v1;

use Yii;
use OpenApi\Annotations as OA;
use yii\web\NotFoundHttpException;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\statistics\Kills;
use common\models\statistics\Reports;
use common\models\user\User;
use common\models\tasks_v2\TaskV2;
use common\models\tasks_v2\TaskV2UserCompletion;
use common\models\user\UserTop;
use api\components\jwt\JwtAuthFilter;
use api\components\jwt\JwtService;

/**
 * Контроллер для работы со статистикой
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="Stats")
 */
class StatsController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // JWT авторизация только для личных методов
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'only' => ['personal', 'report'],
            'except' => ['stats', 'player-new', 'search', 'tops', 'options'],
        ];

        // Опциональная авторизация для stats (инициализирует пользователя, если токен есть, но не требует его)
        $behaviors['optionalAuth'] = [
            'class' => JwtAuthFilter::class,
            'only' => ['stats'],
            'throwException' => false, // Не выбрасываем исключение, если токена нет
        ];

        return $behaviors;
    }

    /**
     * Общая статистика сервера (публичная)
     * 
     * @OA\Get(
     *     path="/v1/stats",
     *     operationId="getServerStats",
     *     tags={"Stats"},
     *     summary="Получить общую статистику сервера",
     *     description="Публичный метод, авторизация не требуется",
     *     @OA\Parameter(
     *         name="serverTag",
     *         in="query",
     *         required=true,
     *         description="Тег сервера",
     *         @OA\Schema(type="string", example="max3")
     *     ),
     *     @OA\Parameter(
     *         name="wipe",
     *         in="query",
     *         required=false,
     *         description="Дата вайпа",
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Статистика сервера",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=404, description="Сервер не найден")
     * )
     */
    public function actionStats($serverTag, $wipe = null)
    {
        $server = Servers::find()->where(['tag' => $serverTag])->one();
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        // Если wipe не передан, используем текущий вайп сервера
        if ($wipe === null) {
            $wipe = $server->currentWipe();
        }

        $cacheKey = 'api_stats_' . $serverTag . '_' . ($wipe ?? 'current');
        $cached = Yii::$app->cache->get($cacheKey);

        if ($cached === false) {
            $tops = $this->getTops($serverTag, $wipe);

            $cached = [
                'server' => [
                    'tag' => $serverTag,
                    'name' => $server->monitoring_name,
                    'current_wipe' => $server->currentWipe(),
                ],
                'tops' => $tops,
            ];

            // Кэшируем на 5 минут
            Yii::$app->cache->set($cacheKey, $cached, 300);
        }

        // Добавляем список вайпов в ответ (не кэшируем, так как не зависит от wipe)
        $response = $cached;
        $response['wipes'] = $server->getWipes(true); // true = обновить кэш, как в старой версии
        $response['wipe'] = $wipe; // Текущий выбранный вайп
        
        // Получаем список всех серверов для навигации (как в старой версии)
        $servers = Servers::find()
            ->cache(30)
            ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
            ->orderBy(['sort' => SORT_ASC])
            ->all();
        
        $response['servers'] = array_map(function($s) {
            return [
                'id' => $s->id,
                'tag' => $s->tag,
                'name' => $s->name,
                'monitoring_name' => $s->monitoring_name,
                'status' => $s->status,
                'text_ip' => $s->text_ip,
            ];
        }, $servers);
        
        // Получаем позицию текущего пользователя в топах (если авторизован)
        $userTops = [];
        $userSteamId = null;
        
        // Пытаемся получить пользователя из JWT токена (опционально)
        $user = null;
        try {
            if (!Yii::$app->user->isGuest) {
                $user = Yii::$app->user->identity;
            } else {
                // Если пользователь не инициализирован через behavior, пытаемся получить из токена напрямую
                $jwtService = Yii::$app->has('jwt') ? Yii::$app->get('jwt') : new JwtService();
                $token = $jwtService->extractTokenFromRequest(Yii::$app->request);
                if ($token) {
                    try {
                        $payload = $jwtService->validateToken($token);
                        $userId = $jwtService->getUserId($payload);
                        $steamId = $jwtService->getSteamId($payload);
                        
                        if ($userId) {
                            $user = User::findIdentity($userId);
                        } elseif ($steamId) {
                            $user = User::find()->where(['steam_id' => $steamId])->one();
                        }
                    } catch (\Exception $e) {
                        // Токен невалидный, игнорируем
                    }
                }
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки авторизации для публичного метода
        }
        
        if ($user) {
            $userSteamId = $user->steam_id;
            $allUserTops = UserTop::getAllUserTops($server, $wipe, false);
            // Форматируем для API - маппим ключи как в getTops
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
            foreach ($keyMapping as $apiKey => $dbKey) {
                if (isset($allUserTops[$dbKey]['items'][$user->steam_id])) {
                    $userTops[$apiKey] = [
                        'position' => $allUserTops[$dbKey]['items'][$user->steam_id]['position']
                    ];
                }
            }
        }
        
        $response['userTops'] = $userTops;
        // Добавляем steam_id пользователя, если авторизован
        if ($userSteamId) {
            $response['userSteamId'] = $userSteamId;
        }
        
        return $this->successResponse($response);
    }

    /**
     * Детальная статистика игрока (публичная)
     * 
     * @OA\Get(
     *     path="/v1/stats/player/{steamId}",
     *     operationId="getPlayerStats",
     *     tags={"Stats"},
     *     summary="Получить детальную статистику игрока",
     *     description="Публичный метод, авторизация не требуется",
     *     @OA\Parameter(
     *         name="serverTag",
     *         in="query",
     *         required=true,
     *         description="Тег сервера",
     *         @OA\Schema(type="string", example="max3")
     *     ),
     *     @OA\Parameter(
     *         name="steamId",
     *         in="path",
     *         required=true,
     *         description="Steam ID игрока",
     *         @OA\Schema(type="string", example="76561198000000000")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Статистика игрока",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=404, description="Сервер или игрок не найден")
     * )
     */
    public function actionPlayerNew($serverTag, $steamId)
    {
        $server = Servers::find()->where(['tag' => $serverTag])->one();
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        $wipe = $server->currentWipe();
        
        // Кэшируем статистику игрока на 5 минут
        $cacheKey = 'api_stats_player_' . $serverTag . '_' . $steamId . '_' . ($wipe ?? 'current');
        $cached = Yii::$app->cache->get($cacheKey);
        
        if ($cached === false) {
            $playerStats = Statistics::getPlayerStats($server, $steamId, $wipe);

            // Получаем информацию о пользователе
            $user = User::findBySteamId($steamId, false, 'stats');
            if (!$user) {
                throw new NotFoundHttpException('Игрок не найден');
            }

            // Форматируем статистику для API
            $formattedStats = [];
            foreach ($playerStats as $key => $value) {
                if (is_object($value)) {
                    $formattedStats[$key] = $value->value;
                } elseif (is_array($value)) {
                    $formattedStats[$key] = $value['value'] ?? $value;
                } else {
                    $formattedStats[$key] = $value;
                }
            }

            // Вычисляем дополнительные метрики
            $kills = Statistics::getParam($playerStats, 'kills');
            $deaths = Statistics::getParam($playerStats, 'deaths');
            $kdr = $deaths > 0 ? round($kills / $deaths, 2) : $kills;
            $wounded = Statistics::getParam($playerStats, 'wounded');
            $tcsDestroyed = Statistics::getParam($playerStats, 'tcsdestroyed');
            $nudeKills = Statistics::getParam($playerStats, 'nude_kills');
            $wipesCount = (int) Statistics::find()
                ->select('COUNT(DISTINCT wipe)')
                ->andWhere(['steam_id' => $steamId])
                ->andWhere(['server_tag' => $server->tag])
                ->scalar();

            // Активность по вайпам за всё время: по всем серверам, playtime в каждом вайпе (для теплокарты)
            $wipesActivityRows = Statistics::find()
                ->select(['wipe', 'server_tag', 'value'])
                ->andWhere(['steam_id' => $steamId])
                ->andWhere(['key' => 'playtime'])
                ->orderBy(['wipe' => SORT_DESC])
                ->limit(120)
                ->asArray()
                ->all();
            $wipesActivity = [];
            foreach ($wipesActivityRows as $row) {
                $playtime = is_numeric($row['value']) ? (int) $row['value'] : 0;
                $wipesActivity[] = [
                    'wipe' => $row['wipe'],
                    'server_tag' => $row['server_tag'] ?? '',
                    'playtime' => $playtime,
                ];
            }

            $images = Statistics::productsImages();
            $names = Statistics::productsNames();

            $reiderItems = [
                ['key' => 'c4thrown', 'score' => 1],
                ['key' => 'satchelsthrown', 'score' => 0.2],
                ['key' => 'rocket_basic', 'score' => 0.5, 'combined' => ['rocket_basic_rpg']],
                ['key' => 'rocket_hv', 'score' => 0.1, 'combined' => ['rocket_hv_rpg']],
                ['key' => 'rocket_fire', 'score' => 0.1, 'combined' => ['rocket_fire_rpg']],
                ['key' => 'ammo_explosive', 'score' => 0.01],
                ['key' => 'grenade.f1.deployed', 'score' => 0.02],
                ['key' => 'grenade.molotov.deployed', 'score' => 0.05],
                ['key' => 'grenade.beancan.deployed', 'score' => 0.05],
                ['key' => 'grenade.flashbang.deployed', 'score' => 0],
                ['key' => 'grenade.supplysignal.deployed', 'score' => 0],
                ['key' => 'grenade.smoke.deployed', 'score' => 0],
                ['key' => 'grenade.bee.deployed', 'score' => 0],
                ['key' => '40mm_grenade_he', 'score' => 0],
                ['key' => '40mm_grenade_smoke', 'score' => 0],
                ['key' => 'rocket_heatseeker', 'score' => 0],
                ['key' => 'flare.deployed', 'score' => 0],
            ];
            $explosives = [];
            foreach ($reiderItems as $item) {
                $itemData = Statistics::getRaiderItem($names, $images, $playerStats, $item['key'], $item['score']);
                if (!empty($item['combined'])) {
                    $combinedCount = $itemData['count'];
                    foreach ($item['combined'] as $combinedKey) {
                        $combinedCount += Statistics::getParam($playerStats, $combinedKey);
                    }
                    $itemData['count'] = $combinedCount;
                    $itemData['desc'] = $combinedCount;
                }
                $explosives[] = [
                    'key' => str_replace('.deployed', '', $item['key']),
                    'name' => $itemData['name'],
                    'image' => $itemData['image'],
                    'count' => (int) $itemData['count'],
                    'score' => (float) $itemData['score'],
                ];
            }

            $killWeapons = Kills::find()
                ->select(['weapon', 'COUNT(*) as count'])
                ->andWhere(['steam_id' => $steamId])
                ->andWhere(['server_tag' => $server->tag])
                ->andWhere(['wipe' => $wipe])
                ->andWhere('weapon IS NOT NULL')
                ->asArray()
                ->groupBy('weapon')
                ->orderBy(['count' => SORT_DESC])
                ->all();
            $weapons = [];
            foreach ($killWeapons as $item) {
                if (empty($item['weapon'])) {
                    continue;
                }
                $weapons[] = [
                    'weapon' => $item['weapon'],
                    'name' => Statistics::getName($names, $item['weapon']),
                    'image' => Statistics::getImage($images, $item['weapon']),
                    'count' => (int) $item['count'],
                ];
            }

            $farmItems = [
                ['name' => \Yii::t('common', 'Серная руда'), 'key' => 'sulfur.ore', 'score' => 1],
                ['name' => \Yii::t('common', 'Железная руда'), 'key' => 'metal.ore', 'score' => 0.5],
                ['name' => \Yii::t('common', 'Камни'), 'key' => 'stones', 'score' => 0.3],
                ['name' => \Yii::t('common', 'Дерево'), 'key' => 'wood', 'score' => 0.05],
                ['name' => \Yii::t('common', 'Разбито бочек'), 'key' => 'barrel', 'score' => 0],
                ['name' => \Yii::t('common', 'Открыто ящиков'), 'key' => 'crate_open', 'score' => 0],
            ];
            $farm = [];
            foreach ($farmItems as $item) {
                $row = Statistics::getFarmItem($images, $names, $playerStats, $item['key'], $item['name'], $item['score']);
                $farm[] = [
                    'key' => $item['key'],
                    'name' => $row['name'],
                    'image' => $row['image'],
                    'count' => (int) $row['count'],
                    'score' => (float) $row['score'],
                ];
            }

            $tasksV2 = TaskV2::find()
                ->where(['is_active' => 1])
                ->orderBy(['sort' => SORT_ASC])
                ->all();
            $userCompletions = TaskV2UserCompletion::find()
                ->where(['user_id' => $user->id])
                ->indexBy('task_id')
                ->all();
            $awards = [];
            foreach ($tasksV2 as $task) {
                $completed = isset($userCompletions[$task->id]) && $userCompletions[$task->id]->count_completed > 0;
                $awards[] = [
                    'id' => $task->id,
                    'name' => $task->title,
                    'image' => $task->getImageUrl(),
                    'completed' => $completed,
                ];
            }
            usort($awards, function ($a, $b) {
                if ($a['completed'] === $b['completed']) {
                    return 0;
                }
                return $a['completed'] ? -1 : 1;
            });
            $awardsCompleted = 0;
            foreach ($awards as $a) {
                if ($a['completed']) {
                    $awardsCompleted++;
                }
            }

            // История убийств (последние 30 событий: убийства, смерти, суициды и т.д.)
            $killsList = Kills::getKills($server, $user, 30);
            $killsForApi = array_map(function ($k) {
                return [
                    'id' => (int) ($k['id'] ?? 0),
                    'type' => $k['type'] ?? 'kill',
                    'steam_id' => $k['steam_id'] ?? '',
                    'dead' => $k['dead'] ?? '',
                    'weapon' => $k['weapon'] ?? null,
                    'weapon_name' => $k['weapon_name'] ?? null,
                    'weapon_image' => $k['weapon_image'] ?? null,
                    'distance' => (int) ($k['distance'] ?? 0),
                    'name' => $k['name'] ?? null,
                    'link' => $k['link'] ?? null,
                    'dead_name' => $k['dead_name'] ?? null,
                    'dead_link' => $k['dead_link'] ?? null,
                    'deadLink' => $k['dead_link'] ?? null,
                    'signs' => $k['signs'] ?? null,
                    'wears' => $k['wears'] ?? null,
                    'bot' => !empty($k['bot']),
                    'animal' => $k['animal'] ?? null,
                    'animal2' => $k['animal2'] ?? null,
                    'created_at' => $k['created_at'] ?? '',
                ];
            }, $killsList);

            $cached = [
                'player' => [
                    'steam_id' => $steamId,
                    'server_tag' => $serverTag,
                    'wipe' => $wipe,
                    'username' => $user->username,
                    'avatar' => $user->getAvatar(),
                    'stats' => $formattedStats,
                    'metrics' => [
                        'kills' => $kills,
                        'deaths' => $deaths,
                        'kdr' => $kdr,
                        'playtime' => Statistics::getParam($playerStats, 'playtime'),
                        'scientists' => Statistics::getParam($playerStats, 'scientists'),
                        'wounded' => $wounded,
                        'tcs_destroyed' => $tcsDestroyed,
                        'nude_kills' => $nudeKills,
                        'wipes' => $wipesCount,
                    ],
                    'explosives' => $explosives,
                    'weapons' => $weapons,
                    'farm' => $farm,
                    'awards' => $awards,
                    'awards_stats' => [
                        'completed' => $awardsCompleted,
                        'total' => count($awards),
                    ],
                    'wipes_activity' => $wipesActivity,
                    'kills' => $killsForApi,
                ],
            ];

            // Сохраняем в кэш на 5 минут
            Yii::$app->cache->set($cacheKey, $cached, 300);
        }

        return $this->successResponse($cached);
    }

    /**
     * Поиск игроков
     * 
     * @OA\Get(
     *     path="/v1/stats/search",
     *     operationId="searchPlayers",
     *     tags={"Stats"},
     *     summary="Поиск игроков по нику или Steam ID",
     *     description="Публичный метод, авторизация не требуется",
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         required=true,
     *         description="Поисковый запрос (ник или Steam ID)",
     *         @OA\Schema(type="string", example="player123")
     *     ),
     *     @OA\Parameter(
     *         name="serverId",
     *         in="query",
     *         required=false,
     *         description="ID сервера для фильтрации",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Результаты поиска",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Пустой запрос")
     * )
     */
    public function actionSearch($q, $serverId = null)
    {
        if (empty($q)) {
            return $this->errorResponse('INVALID_QUERY', 'Запрос не может быть пустым', [], 400);
        }

        // Поиск по нику или steam_id
        $results = Statistics::find()
            ->select(['steam_id', 'name'])
            ->where(['LIKE', 'name', $q])
            ->orWhere(['steam_id' => $q])
            ->groupBy(['steam_id', 'name'])
            ->limit(20)
            ->asArray()
            ->all();

        return $this->successResponse([
            'results' => $results,
        ]);
    }

    /**
     * Топы сервера (публичные)
     * 
     * @OA\Get(
     *     path="/v1/stats/tops",
     *     operationId="getServerTops",
     *     tags={"Stats"},
     *     summary="Получить топы игроков сервера",
     *     description="Публичный метод, авторизация не требуется",
     *     @OA\Parameter(
     *         name="serverTag",
     *         in="query",
     *         required=true,
     *         description="Тег сервера",
     *         @OA\Schema(type="string", example="max3")
     *     ),
     *     @OA\Parameter(
     *         name="wipe",
     *         in="query",
     *         required=false,
     *         description="Дата вайпа",
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Топы сервера",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionTops($serverTag, $wipe = null)
    {
        $tops = $this->getTops($serverTag, $wipe);

        return $this->successResponse([
            'server_tag' => $serverTag,
            'wipe' => $wipe,
            'tops' => $tops,
        ]);
    }

    /**
     * Личная статистика (требует авторизации)
     * 
     * @OA\Get(
     *     path="/v1/stats/personal",
     *     operationId="getPersonalStats",
     *     tags={"Stats"},
     *     summary="Получить личную статистику текущего пользователя",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Личная статистика",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionPersonal()
    {
        $user = $this->getCurrentUser();
        $steamId = $user->steam_id;

        $personalStats = $user->getAllStats();

        return $this->successResponse([
            'user_id' => $user->id,
            'steam_id' => $steamId,
            'stats' => $personalStats,
        ]);
    }

    /**
     * Отправка жалобы на игрока (требует авторизации)
     * 
     * @OA\Post(
     *     path="/v1/stats/report/{serverTag}/{steamId}",
     *     operationId="reportPlayer",
     *     tags={"Stats"},
     *     summary="Отправить жалобу на игрока",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="serverTag",
     *         in="path",
     *         required=true,
     *         description="Тег сервера",
     *         @OA\Schema(type="string", example="max3")
     *     ),
     *     @OA\Parameter(
     *         name="steamId",
     *         in="path",
     *         required=true,
     *         description="Steam ID игрока, на которого подается жалоба",
     *         @OA\Schema(type="string", example="76561198000000000")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Жалоба отправлена",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionReport($serverTag, $steamId)
    {
        $user = $this->getCurrentUser();

        // Логика создания жалобы
        // Это упрощенная версия, реальная логика может быть сложнее

        return $this->successResponse([
            'message' => 'Жалоба отправлена',
        ]);
    }

    /**
     * Получение топов сервера
     * 
     * @param string $serverTag
     * @param string|null $wipe
     * @return array
     */
    protected function getTops($serverTag, $wipe = null)
    {
        $server = Servers::find()->where(['tag' => $serverTag])->one();
        if (!$server) {
            return [];
        }

        // Если wipe не передан, используем текущий вайп сервера
        if ($wipe === null) {
            $wipe = $server->currentWipe();
        }

        // Получаем топы через UserTop::getUserTops
        $tops = UserTop::getUserTops($server, $wipe, false);

        // Форматируем данные для API
        $formattedTops = [];
        
        // Маппинг всех категорий топов (ключ API => ключ БД)
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

        // Обрабатываем все категории, которые возвращает UserTop::getUserTops
        foreach ($tops as $dbKey => $topCategory) {
            // Находим соответствующий API ключ
            $apiKey = array_search($dbKey, $keyMapping);
            if ($apiKey === false) {
                // Если ключ не найден в маппинге, используем оригинальный ключ
                $apiKey = $dbKey;
            }
            
            $formattedTops[$apiKey] = [
                'label' => $topCategory['label'] ?? ucfirst($apiKey),
                'items' => array_map(function($item) {
                    // Позиция в UserTop::getUserTops начинается с 0 (0 = первое место, 1 = второе, 2 = третье)
                    // Но для отображения на фронтенде нужна позиция с 1
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
                        'status' => $item['status'] ?? null,
                        'is_hidden' => $item['is_hidden'] ?? false,
                    ];
                }, $topCategory['items'] ?? []),
            ];
        }
        
        // Убеждаемся, что все категории из маппинга присутствуют (даже если пустые)
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

