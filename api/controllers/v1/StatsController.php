<?php

namespace api\controllers\v1;

use Yii;
use OpenApi\Annotations as OA;
use yii\web\NotFoundHttpException;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\statistics\Reports;
use common\models\user\User;
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

        return $this->successResponse([
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
                ],
            ],
        ]);
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

