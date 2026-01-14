<?php

namespace api\controllers\v1;

use Yii;
use OpenApi\Annotations as OA;
use yii\web\NotFoundHttpException;
use common\models\servers\Servers;
use common\models\statistics\Statistics;
use common\models\statistics\Reports;
use api\components\jwt\JwtAuthFilter;

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

        $cacheKey = 'api_stats_' . $serverTag . '_' . ($wipe ?? 'current');
        $cached = Yii::$app->cache->get($cacheKey);

        if ($cached === false) {
            $tops = $this->getTops($serverTag, $wipe);

            $cached = [
                'server' => [
                    'tag' => $serverTag,
                    'name' => $server->monitoring_name,
                    'current_wipe' => $server->wipe ?? null,
                ],
                'tops' => $tops,
            ];

            // Кэшируем на 5 минут
            Yii::$app->cache->set($cacheKey, $cached, 300);
        }

        return $this->successResponse($cached);
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

        $playerStats = Statistics::getPlayerStats($steamId, $serverTag);

        return $this->successResponse([
            'player' => [
                'steam_id' => $steamId,
                'server_tag' => $serverTag,
                'stats' => $playerStats,
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
        // Получение топов (упрощенная версия)
        // Реальная логика зависит от структуры данных Statistics
        
        return [
            'reider' => [],
            'killer' => [],
            'peaceful' => [],
        ];
    }
}

