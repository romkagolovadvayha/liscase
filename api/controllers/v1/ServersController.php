<?php

namespace api\controllers\v1;

use Yii;
use yii\web\NotFoundHttpException;
use common\models\servers\Servers;
use common\models\servers\ServersTags;
use common\models\stats\Wipe;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с серверами
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="Servers")
 */
class ServersController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // Все методы публичные, JWT не требуется
        return $behaviors;
    }

    /**
     * Список всех серверов
     * 
     * @OA\Get(
     *     path="/v1/servers",
     *     operationId="getServers",
     *     tags={"Servers"},
     *     summary="Получить список всех серверов",
     *     description="Публичный метод, авторизация не требуется",
     *     @OA\Response(
     *         response=200,
     *         description="Список серверов",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionIndex()
    {
        $cacheKey = 'api_servers_index';
        $cached = Yii::$app->cache->get($cacheKey);

        if ($cached === false) {
            $servers = Servers::find()
                ->with(['serversTags', 'mapEntity', 'mapList'])
                ->where(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
                ->orderBy(['sort' => SORT_ASC])
                ->all();

            $projectStats = \common\models\statistics\Statistics::projectStats();

            $serversData = [];
            foreach ($servers as $server) {
                $serversData[] = $this->formatServer($server);
            }

            $cached = [
                'servers' => $serversData,
                'projectStats' => $projectStats,
            ];

            // Кэшируем на 180 секунд
            Yii::$app->cache->set($cacheKey, $cached, 180);
        }

        return $this->successResponse($cached);
    }

    /**
     * Детальная информация о сервере
     * 
     * @OA\Get(
     *     path="/v1/servers/{tag}",
     *     operationId="getServerByTag",
     *     tags={"Servers"},
     *     summary="Получить детальную информацию о сервере по тегу",
     *     description="Публичный метод, авторизация не требуется",
     *     @OA\Parameter(
     *         name="tag",
     *         in="path",
     *         required=true,
     *         description="Тег сервера",
     *         @OA\Schema(type="string", example="max3")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Детальная информация о сервере",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=404, description="Сервер не найден")
     * )
     */
    public function actionView($tag)
    {
        $server = Servers::find()
            ->where(['tag' => $tag])
            ->with(['serversTags', 'mapEntity', 'mapList'])
            ->one();

        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        return $this->successResponse($this->formatServer($server, true));
    }

    /**
     * Серверы по тегу/категории
     * 
     * @OA\Get(
     *     path="/v1/servers/tag/{tagLink}",
     *     operationId="getServersByTag",
     *     tags={"Servers"},
     *     summary="Получить список серверов по тегу/категории",
     *     description="Публичный метод, авторизация не требуется",
     *     @OA\Parameter(
     *         name="tagLink",
     *         in="path",
     *         required=true,
     *         description="Ссылка на тег/категорию",
     *         @OA\Schema(type="string", example="official")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список серверов по тегу",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=404, description="Тег не найден")
     * )
     */
    public function actionTag($tagLink)
    {
        $tag = ServersTags::find()->where(['link' => $tagLink])->one();
        if (!$tag) {
            throw new NotFoundHttpException('Тег не найден');
        }

        $servers = Servers::find()
            ->innerJoinWith('serversTags')
            ->where(['servers_tags.id' => $tag->id])
            ->andWhere(['IN', 'servers.status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT]])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        $serversData = [];
        foreach ($servers as $server) {
            $serversData[] = $this->formatServer($server);
        }

        return $this->successResponse([
            'tag' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'link' => $tag->link,
            ],
            'servers' => $serversData,
        ]);
    }

    /**
     * Правила сервера
     * 
     * @OA\Get(
     *     path="/v1/servers/{serverTag}/rules",
     *     operationId="getServerRules",
     *     tags={"Servers"},
     *     summary="Получить правила сервера",
     *     description="Публичный метод, авторизация не требуется",
     *     @OA\Parameter(
     *         name="serverTag",
     *         in="path",
     *         required=true,
     *         description="Тег сервера",
     *         @OA\Schema(type="string", example="max3")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Правила сервера",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=404, description="Сервер не найден")
     * )
     */
    public function actionRules($serverTag)
    {
        $server = Servers::find()->where(['tag' => $serverTag])->one();
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        // Получаем правила из новой таблицы
        $rules = \common\models\servers\ServersRules::getRulesForServer($server->id);
        
        // Группируем по категориям
        $categories = [];
        foreach ($rules as $rule) {
            $categoryId = $rule->category_id;
            if (!isset($categories[$categoryId])) {
                $category = $rule->category;
                $categories[$categoryId] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'icon' => $category->icon,
                    'sort' => $category->sort,
                    'rules' => [],
                ];
            }
            
            $categories[$categoryId]['rules'][] = [
                'id' => $rule->id,
                'title' => $rule->title,
                'content' => $rule->content,
                'punishment' => $rule->punishment,
                'sort' => $rule->sort,
            ];
        }
        
        // Сортируем категории по sort
        usort($categories, function($a, $b) {
            return $a['sort'] <=> $b['sort'];
        });
        
        // Сортируем правила внутри каждой категории по sort
        foreach ($categories as &$category) {
            usort($category['rules'], function($a, $b) {
                return $a['sort'] <=> $b['sort'];
            });
        }
        unset($category);

        return $this->successResponse([
            'server_tag' => $serverTag,
            'server_id' => $server->id,
            'categories' => array_values($categories),
        ]);
    }

    /**
     * Информация о вайпах сервера
     * 
     * @OA\Get(
     *     path="/v1/servers/{serverTag}/wipe-info",
     *     operationId="getServerWipeInfo",
     *     tags={"Servers"},
     *     summary="Получить информацию о вайпах сервера",
     *     description="Публичный метод, авторизация не требуется",
     *     @OA\Parameter(
     *         name="serverTag",
     *         in="path",
     *         required=true,
     *         description="Тег сервера",
     *         @OA\Schema(type="string", example="max3")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Информация о вайпах",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=404, description="Сервер не найден")
     * )
     */
    public function actionWipeInfo($serverTag)
    {
        $server = Servers::find()->where(['tag' => $serverTag])->one();
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        $wipes = Wipe::find()
            ->where(['server_tag' => $serverTag])
            ->orderBy(['wipe_date' => SORT_DESC])
            ->limit(10)
            ->all();

        $wipesData = [];
        foreach ($wipes as $wipe) {
            $wipesData[] = [
                'wipe_date' => $wipe->wipe_date,
                'next_wipe' => $wipe->next_wipe,
                'wipe_type' => $wipe->wipe_type,
            ];
        }

        return $this->successResponse([
            'server_tag' => $serverTag,
            'current_wipe' => $server->wipe ?? null,
            'next_wipe' => $server->next_wipe ?? null,
            'wipes_history' => $wipesData,
        ]);
    }

    /**
     * Блок с информацией о вайпе (для виджета)
     * 
     * @OA\Get(
     *     path="/v1/servers/wipe-block",
     *     operationId="getWipeBlock",
     *     tags={"Servers"},
     *     summary="Получить блок информации о вайпах всех серверов",
     *     description="Публичный метод, авторизация не требуется. Используется для виджета на сайте.",
     *     @OA\Response(
     *         response=200,
     *         description="Блок информации о вайпах",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionWipeBlock()
    {
        $servers = Servers::find()
            ->where(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT]])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        $wipeBlocks = [];
        foreach ($servers as $server) {
            $wipeBlocks[] = [
                'tag' => $server->tag,
                'name' => $server->monitoring_name,
                'current_wipe' => $server->wipe ?? null,
                'next_wipe' => $server->next_wipe ?? null,
            ];
        }

        return $this->successResponse($wipeBlocks);
    }

    /**
     * Форматирование данных сервера
     * 
     * @param Servers $server
     * @param bool $detailed Детальная информация
     * @return array
     */
    protected function formatServer($server, $detailed = false)
    {
        // Статус как число: 0 - неактивен, 1 - активен, 2 - ожидание
        $statusNumber = $server->status;
        
        // Используем метод monitoring() из модели для вычисления процентов
        $monitoring = $server->monitoring();
        
        $data = [
            'id' => $server->id,
            'tag' => $server->tag,
            'name' => $server->monitoring_name,
            'monitoring_name' => $server->monitoring_name,
            'description' => $server->monitoring_description ?? '',
            'monitoring_description' => $server->monitoring_description ?? '',
            'status' => $statusNumber, // Число для совместимости с фронтендом
            'players' => (int)$server->players,
            'max' => (int)$server->max,
            'joined' => (int)($server->joined ?? 0),
            'queued' => (int)($server->queued ?? 0),
            'ip' => $server->ip,
            'text_ip' => $server->text_ip,
            'port' => (int)$server->port,
            'minMapSize' => (int)$server->min_map_size,
            'maxMapSize' => (int)$server->max_map_size,
            'nextWipe' => $server->next_wipe,
            'nextWipeTimestamp' => $server->next_wipe ? (($timestamp = strtotime($server->next_wipe)) !== false ? $timestamp : null) : null,
            'wipeType' => $server->wipeTypeText() ?? 'Вайп',
            'monitoring' => [
                'percentPlayers' => $monitoring['percentPlayers'] ?? 0,
                'percentJoined' => $monitoring['percentJoined'] ?? 0,
                'percentQueued' => $monitoring['percentQueued'] ?? 0,
                'percentPlayersAbsolute' => $monitoring['percentPlayersAbsolute'] ?? 0,
                'percentJoinedAbsolute' => $monitoring['percentJoinedAbsolute'] ?? 0,
                'percentQueuedAbsolute' => $monitoring['percentQueuedAbsolute'] ?? 0,
            ],
        ];

        if ($server->mapEntity) {
            $data['map'] = [
                'id' => $server->mapEntity->id,
                'name' => $server->mapEntity->name,
                'size' => $server->mapEntity->size ?? null,
                'image' => $server->mapEntity->image ?? null,
            ];
        }

        if ($server->serversTags) {
            $data['tags'] = [];
            foreach ($server->serversTags as $tag) {
                $data['tags'][] = [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'title' => $tag->title,
                    'link' => $tag->link,
                    'link_name' => $tag->link_name,
                    'color' => $tag->color,
                    'icon' => $tag->icon,
                ];
            }
        }

        if ($detailed) {
            $data['current_wipe'] = $server->wipe ?? null;
            $data['sort'] = $server->sort ?? null;
        }

        return $data;
    }

    /**
     * Получение статуса сервера в виде строки
     * 
     * @param int $status
     * @return string
     */
    protected function getServerStatus($status)
    {
        switch ($status) {
            case Servers::STATUS_ACTIVE:
                return 'active';
            case Servers::STATUS_WAIT:
                return 'wait';
            case Servers::STATUS_NOACTIVE:
                return 'noactive';
            default:
                return 'unknown';
        }
    }
}

