<?php

namespace api\controllers\v1;

use Yii;
use yii\web\NotFoundHttpException;
use common\helpers\ApiPublicCacheTtl;
use common\helpers\ServersCacheHelper;
use common\models\box\Drop;
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
        if ($cached !== false) {
            return $this->successResponse($cached);
        }

        // Смягчаем «cache stampede»: при истечении TTL несколько воркеров не должны одновременно грузить БД.
        $lockKey = $cacheKey . '_building';
        $lockOk = Yii::$app->cache->add($lockKey, 1, 30);
        if (!$lockOk) {
            // До ~6 с: при медленной БД первый воркер может долго собирать payload; 2 с раньше давали лишние параллельные сборки.
            for ($i = 0; $i < 120; $i++) {
                usleep(50000);
                $cached = Yii::$app->cache->get($cacheKey);
                if ($cached !== false) {
                    return $this->successResponse($cached);
                }
            }
        }

        try {
            $cached = ServersCacheHelper::buildIndexPayload(Yii::$app->language);
            Yii::$app->cache->set($cacheKey, $cached, ServersCacheHelper::CACHE_TTL);
        } catch (\Throwable $e) {
            Yii::error(
                'api_servers_index build failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString(),
                'api'
            );

            return $this->errorResponse(
                'SERVICE_UNAVAILABLE',
                YII_DEBUG ? $e->getMessage() : 'Service temporarily unavailable. Please retry.',
                [],
                503
            );
        } finally {
            if ($lockOk) {
                Yii::$app->cache->delete($lockKey);
            }
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
            
            Yii::$app->cache->set($cacheKey, $cached, ApiPublicCacheTtl::SECONDS);
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

            Yii::$app->cache->set($cacheKey, $cached, ApiPublicCacheTtl::SECONDS);
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

        Yii::$app->cache->set($cacheKey, $result, ApiPublicCacheTtl::SECONDS);

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
     * Предметы вайп-блока (сгруппированы по времени блокировки в часах)
     *
     * @OA\Get(
     *     path="/v1/servers/wipe-block/items",
     *     operationId="getWipeBlockItems",
     *     tags={"Servers"},
     *     summary="Получить список предметов вайп-блока",
     *     description="Публичный метод, авторизация не требуется.",
     *     @OA\Response(
     *         response=200,
     *         description="Список предметов вайп-блока по группам blocked_hour",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionWipeBlockItems()
    {
        $cacheKey = 'api_servers_wipe_block_items_v2_64_' . Yii::$app->language;
        $cached = Yii::$app->cache->get($cacheKey);

        if ($cached !== false) {
            return $this->successResponse($cached);
        }

        $drops = Drop::find()
            ->andWhere(['market_status' => Drop::MARKET_STATUS_ACTIVE])
            ->andWhere('blocked_hour IS NOT NULL')
            ->orderBy(['blocked_hour' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $images = Drop::productsImages();
        $groups = [];

        foreach ($drops as $drop) {
            $blockedHour = (int)$drop->blocked_hour;
            $groupKey = (string)$blockedHour;

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'blocked_hour' => $blockedHour,
                    'items' => [],
                ];
            }

            $imgRow = $images[$drop->id] ?? [];
            $imageUrl = (string)($imgRow['64px'] ?? '');
            if ($imageUrl === '') {
                $imageUrl = (string)($imgRow['150px'] ?? '');
            }
            if ($imageUrl === '' && $drop->imageOrig) {
                $imageUrl = $drop->imageOrig->getImagePubUrl();
            }

            $groups[$groupKey]['items'][] = [
                'id' => (int)$drop->id,
                'name' => Yii::t('database', $drop->name ?: ''),
                'image' => (string)$imageUrl,
                'rust_id' => $drop->rust_id !== null ? (string)$drop->rust_id : '',
            ];
        }

        $result = array_values($groups);
        Yii::$app->cache->set($cacheKey, $result, ApiPublicCacheTtl::SECONDS);

        return $this->successResponse($result);
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
        return ServersCacheHelper::formatServer($server, $detailed);
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

