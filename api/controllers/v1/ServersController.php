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
        $cacheKey = 'api_servers_index_' . Yii::$app->language;
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
        // Кэшируем данные сервера на 3 минуты (formatServer использует Yii::t — ключ с языком)
        $cacheKey = 'api_servers_view_' . $tag . '_' . Yii::$app->language;
        $cached = Yii::$app->cache->get($cacheKey);

        if ($cached === false) {
            $server = Servers::find()
                ->where(['tag' => $tag])
                ->with(['serversTags', 'mapEntity', 'mapList'])
                ->one();

            if (!$server) {
                throw new NotFoundHttpException('Сервер не найден');
            }

            $cached = $this->formatServer($server, true);
            
            // Кэшируем на 180 секунд
            Yii::$app->cache->set($cacheKey, $cached, 180);
        }

        return $this->successResponse($cached);
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
        // Кэшируем данные на 3 минуты (по языку — name/description переводятся)
        $cacheKey = 'api_servers_tag_' . $tagLink . '_' . Yii::$app->language;
        $cached = Yii::$app->cache->get($cacheKey);

        if ($cached === false) {
            $tag = ServersTags::find()->where(['link_name' => $tagLink])->one();
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

            $cached = [
                'tag' => [
                    'id' => $tag->id,
                    'name' => Yii::t('database', $tag->name ?: ''),
                    'title' => Yii::t('database', $tag->title ?: $tag->name ?: ''),
                    'link_name' => $tag->link_name,
                    'link' => $tag->link_name,
                    'short_description' => Yii::t('database', $tag->short_description ?: ''),
                    'description' => Yii::t('database', $tag->description ?: ''),
                    'color' => $tag->color ?? null,
                ],
                'servers' => $serversData,
            ];

            // Кэшируем на 180 секунд (по языку — name/description переводятся)
            Yii::$app->cache->set($cacheKey, $cached, 180);
        }

        return $this->successResponse($cached);
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
        // Кэшируем правила на 10 минут (по языку — category/rule текст переводится)
        $cacheKey = 'api_servers_rules_' . $serverTag . '_' . Yii::$app->language;
        $cached = Yii::$app->cache->get($cacheKey);

        if ($cached !== false) {
            return $this->successResponse($cached);
        }

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
                    'name' => Yii::t('database', $category->name ?: ''),
                    'icon' => $category->icon,
                    'sort' => $category->sort,
                    'no_numbering' => (bool)$category->no_numbering,
                    'rules' => [],
                ];
            }
            
            $categories[$categoryId]['rules'][] = [
                'id' => $rule->id,
                'title' => Yii::t('database', $rule->title ?: ''),
                'content' => Yii::t('database', $rule->content ?: ''),
                'punishment' => $rule->punishment ? Yii::t('database', $rule->punishment) : null,
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

        $result = [
            'server_tag' => $serverTag,
            'server_id' => $server->id,
            'categories' => array_values($categories),
        ];

        // Сохраняем в кэш на 10 минут (600 секунд)
        Yii::$app->cache->set($cacheKey, $result, 600);

        return $this->successResponse($result);
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
                'name' => Yii::t('database', $server->name ?: $server->monitoring_name ?: ''),
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
            'name' => Yii::t('database', $server->name ?: $server->monitoring_name ?: ''),
            'monitoring_name' => Yii::t('database', $server->monitoring_name ?: ''),
            'description' => Yii::t('database', $server->monitoring_description ?? ''),
            'monitoring_description' => Yii::t('database', $server->monitoring_description ?? ''),
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
            'wipe_type' => (int)($server->wipe_type ?? 0),
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

        // Текущая карта: приоритет mapList (картинка большего размера для качества), иначе mapEntity
        if ($server->mapList) {
            $imagePath = $server->mapList->image ?? $server->mapList->image_preview ?? null;
            $data['map'] = [
                'id' => $server->mapList->id,
                'name' => $server->mapList->hash ?? $server->mapList->name ?? null,
                'size' => $server->mapList->size ?? $server->mapList->size_int ?? null,
                'seed' => $server->mapList->seed ?? null,
                'image' => $this->getMapImageUrl($imagePath),
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

    /**
     * Формирует публичный URL изображения карты (S3 или как есть для полного URL).
     * Логика как в MapsController::getMapImageUrl().
     *
     * @param string|null $path Путь к изображению
     * @return string|null
     */
    private function getMapImageUrl(?string $path): ?string
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

