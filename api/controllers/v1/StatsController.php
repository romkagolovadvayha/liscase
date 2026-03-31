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
use common\models\teams\Teams as TeamsModel;
use common\helpers\StatsCacheHelper;
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
     * URL статической картинки (например, охота) из S3 или baseUrl.
     * Дублирует логику Statistics::getStaticImageUrl для совместимости со старым деплоем common.
     * @param string $path путь без ведущего слэша, например images/hunters/Boar.png
     * @return string
     */
    private static function getStaticImageUrl($path)
    {
        $path = ltrim($path, '/');
        if (Yii::$app->has('s3Api')) {
            return Yii::$app->s3Api->getPublicUrl($path);
        }
        $baseUrl = Yii::$app->settings->get('s3_publicUrl');
        return $baseUrl ? rtrim($baseUrl, '/') . '/' . $path : '/' . $path;
    }

    /**
     * Просматривает ли текущий запрос (JWT) профиль того же пользователя.
     */
    private function isViewerProfileOwner(string $profileSteamId): bool
    {
        $identity = Yii::$app->user->identity;
        if ($identity === null) {
            return false;
        }
        return (string) $identity->steam_id === (string) $profileSteamId;
    }

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
            'except' => ['stats', 'player-new', 'player-resources', 'player-kills', 'player-team', 'player-loot-crafts', 'duels', 'search', 'tops', 'options'],
        ];

        // Опциональная авторизация: токен разбирается при наличии (нужно, чтобы владелец профиля видел скрытую от других команду)
        $behaviors['optionalAuth'] = [
            'class' => JwtAuthFilter::class,
            'only' => ['stats', 'player-new', 'player-team'],
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
        // Один запрос: все серверы (кэш 30 сек), нужный — по tag из списка
        $servers = Servers::find()
            ->cache(30)
            ->andWhere(['IN', 'status', [Servers::STATUS_ACTIVE, Servers::STATUS_WAIT, Servers::STATUS_NOACTIVE]])
            ->orderBy(['sort' => SORT_ASC])
            ->all();

        $server = null;
        foreach ($servers as $s) {
            if ($s->tag === $serverTag) {
                $server = $s;
                break;
            }
        }
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        if ($wipe === null) {
            $wipe = $server->currentWipe();
        }

        $cacheKey = StatsCacheHelper::cacheKey($serverTag, $wipe);
        $cached = Yii::$app->cache->get($cacheKey);

        if ($cached === false) {
            $cached = StatsCacheHelper::buildPayload($server, $wipe);
            Yii::$app->cache->set($cacheKey, $cached, StatsCacheHelper::CACHE_TTL);
        }

        $response = $cached;
        $response['wipes'] = $server->getWipes(false);
        $response['wipe'] = $wipe;
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
    /**
     * Данные для вкладки «Ресурсы и добыча»: farm, hunters, ferm, fishing, craftable_images.
     * Вызывается только при открытии вкладки (оптимизация).
     *
     * @param \common\models\servers\Servers $server
     * @param \common\models\user\User $user
     * @param string|null $wipe
     * @param bool $periodAll
     * @param array $playerStats результат Statistics::getPlayerStats / getPlayerStatsAllTime
     * @return array{farm: array, hunters: array, ferm: array, fishing: array, craftable_images: array}
     */
    private function buildPlayerResourcesData($server, $user, $wipe, $periodAll, $playerStats)
    {
        $images = Statistics::productsImages();
        $names = Statistics::productsNames();

        $farmItems = [
            ['name' => \Yii::t('common', 'Серная руда'), 'key' => 'sulfur.ore', 'score' => 1],
            ['name' => \Yii::t('common', 'Железная руда'), 'key' => 'metal.ore', 'score' => 0.5],
            ['name' => \Yii::t('common', 'Камни'), 'key' => 'stones', 'score' => 0.3],
            ['name' => \Yii::t('common', 'Дерево'), 'key' => 'wood', 'score' => 0.05],
            ['name' => \Yii::t('common', 'Дизельная бочка'), 'key' => 'diesel_barrel', 'score' => 0],
            ['name' => \Yii::t('common', 'Обломки костей'), 'key' => 'bones', 'param' => 'bone.fragments', 'image_key' => 'bone.fragments', 'score' => 0],
            ['name' => \Yii::t('common', 'Животный жир'), 'key' => 'animal_fat', 'param' => 'fat.animal', 'image_key' => 'fat.animal', 'score' => 0],
            ['name' => \Yii::t('common', 'Кожа'), 'key' => 'leather', 'score' => 0],
            ['name' => \Yii::t('common', 'Скрап'), 'key' => 'scrap', 'score' => 0],
        ];
        $farm = [];
        foreach ($farmItems as $item) {
            $statKey = $item['param'] ?? $item['key'];
            $row = Statistics::getFarmItem($images, $names, $playerStats, $statKey, $item['name'], $item['score']);
            $imageKey = $item['image_key'] ?? $item['key'];
            $count = (int) $row['count'];
            $farm[] = [
                'key' => $item['key'],
                'name' => $row['name'],
                'image' => Statistics::getImage($images, $imageKey),
                'count' => $count,
                'score' => (float) $row['score'],
            ];
        }

        $hunterItems = [
            ['key' => 'boar', 'name' => \Yii::t('common', 'Кабаны'), 'image_path' => 'images/hunters/Boar.png'],
            ['key' => 'horse', 'name' => \Yii::t('common', 'Лошади'), 'image_path' => 'images/hunters/Horse.png'],
            ['key' => 'wolf', 'name' => \Yii::t('common', 'Волки'), 'param' => ['wolf', 'wolf2', 'skull.wolf'], 'image_path' => 'images/hunters/Wolf.png'],
            ['key' => 'bear', 'name' => \Yii::t('common', 'Медведи'), 'param' => ['bear', 'polarbear'], 'image_path' => 'images/hunters/bear.png'],
            ['key' => 'deer', 'name' => \Yii::t('common', 'Олени'), 'param' => ['deer', 'stag'], 'image_path' => 'images/hunters/Stag.png'],
            ['key' => 'chicken', 'name' => \Yii::t('common', 'Курицы'), 'image_path' => 'images/hunters/Chicken.png'],
            ['key' => 'simpleshark', 'name' => \Yii::t('common', 'Акулы'), 'image_path' => 'images/hunters/shark2.png'],
            ['key' => 'panther', 'name' => \Yii::t('common', 'Пантеры'), 'image_path' => 'images/hunters/panther.png'],
            ['key' => 'crocodile', 'name' => \Yii::t('common', 'Крокодилы'), 'image_path' => 'images/hunters/crocodile.png'],
            ['key' => 'tiger', 'name' => \Yii::t('common', 'Тигры'), 'image_path' => 'images/hunters/tiger.png'],
            ['key' => 'snake', 'name' => \Yii::t('common', 'Змеи'), 'param' => ['snake.entity'], 'product_image_key' => 'snake.entity'],
        ];
        $deathsByAnimal = Kills::getDeathsByAnimalCounts($user, $periodAll ? null : $server, $wipe, $periodAll);
        $hunters = [];
        foreach ($hunterItems as $item) {
            $count = 0;
            if (!empty($item['param'])) {
                foreach ((array) $item['param'] as $p) {
                    $count += (int) Statistics::getParam($playerStats, $p);
                }
            } else {
                $count = (int) Statistics::getParam($playerStats, $item['key']);
            }
            $killedPlayer = 0;
            if (!empty($item['param'])) {
                foreach ((array) $item['param'] as $p) {
                    $killedPlayer += (int) (isset($deathsByAnimal[$p]) ? $deathsByAnimal[$p] : 0);
                }
            } else {
                $killedPlayer = (int) (isset($deathsByAnimal[$item['key']]) ? $deathsByAnimal[$item['key']] : 0);
            }
            $imageUrl = !empty($item['product_image_key'])
                ? Statistics::getImage($images, $item['product_image_key'])
                : self::getStaticImageUrl($item['image_path']);
            $hunters[] = [
                'key' => $item['key'],
                'name' => $item['name'],
                'image' => $imageUrl,
                'count' => $count,
                'killed_player' => $killedPlayer,
                'score' => 0,
            ];
        }

        $fermItems = [
            ['name' => \Yii::t('common', 'Ткань'), 'key' => 'gathered_cloth', 'score' => 0.05],
            ['name' => \Yii::t('common', 'Кукуруза'), 'key' => 'gathered_corn', 'score' => 0.3],
            ['name' => \Yii::t('common', 'Картофель'), 'key' => 'gathered_potato', 'score' => 0.4],
            ['name' => \Yii::t('common', 'Тыква'), 'key' => 'gathered_pumpkin', 'score' => 0.5],
            ['name' => \Yii::t('common', 'Синие ягоды'), 'key' => 'gathered_blue.berry', 'score' => 0.5],
            ['name' => \Yii::t('common', 'Желтые ягоды'), 'key' => 'gathered_yellow.berry', 'score' => 0.5],
            ['name' => \Yii::t('common', 'Красные ягоды'), 'key' => 'gathered_red.berry', 'score' => 0.5],
            ['name' => \Yii::t('common', 'Белые ягоды'), 'key' => 'gathered_white.berry', 'score' => 0.5],
            ['name' => \Yii::t('common', 'Зеленые ягоды'), 'key' => 'gathered_green.berry', 'score' => 0.5],
            ['name' => \Yii::t('common', 'Черные ягоды'), 'key' => 'gathered_black.berry', 'score' => 1],
            ['name' => \Yii::t('common', 'Орхидея'), 'key' => 'gathered_orchid', 'score' => 0.3],
            ['name' => \Yii::t('common', 'Розы'), 'key' => 'gathered_rose', 'score' => 0.3],
            ['name' => \Yii::t('common', 'Подсолнух'), 'key' => 'gathered_sunflower', 'score' => 0.3],
            ['name' => \Yii::t('common', 'Пшеница'), 'key' => 'gathered_wheat', 'score' => 0.3],
        ];
        $ferm = [];
        foreach ($fermItems as $item) {
            $row = Statistics::getFermItem($images, $playerStats, $item['key'], $item['name'], $item['score']);
            $ferm[] = [
                'key' => $item['key'],
                'name' => $row['name'],
                'image' => $row['image'],
                'count' => (int) $row['count'],
                'score' => (float) $item['score'],
            ];
        }

        $fishingItems = [
            ['name' => \Yii::t('common', 'Акула'), 'key' => 'f_fish.smallshark', 'score' => 45],
            ['name' => \Yii::t('common', 'Большеголов'), 'key' => 'f_fish.orangeroughy', 'score' => 37],
            ['name' => \Yii::t('common', 'Сом'), 'key' => 'f_fish.catfish', 'score' => 32],
            ['name' => \Yii::t('common', 'Окунь'), 'key' => 'f_fish.yellowperch', 'score' => 25],
            ['name' => \Yii::t('common', 'Лосось'), 'key' => 'f_fish.salmon', 'score' => 22],
            ['name' => \Yii::t('common', 'Форель'), 'key' => 'f_fish.troutsmall', 'score' => 15],
            ['name' => \Yii::t('common', 'Анчоус'), 'key' => 'f_fish.anchovy', 'score' => 10],
            ['name' => \Yii::t('common', 'Сельдь'), 'key' => 'f_fish.herring', 'score' => 10],
            ['name' => \Yii::t('common', 'Сардина'), 'key' => 'f_fish.sardine', 'score' => 10],
        ];
        $fishing = [];
        foreach ($fishingItems as $item) {
            $row = Statistics::getFishItem($images, $playerStats, $item['key'], $item['name'], $item['score']);
            $fishing[] = [
                'key' => $item['key'],
                'name' => $row['name'],
                'image' => $row['image'],
                'count' => (int) $row['count'],
                'score' => (float) $item['score'],
            ];
        }

        $craftableImages = [
            'metal_fragments' => Statistics::getImage($images, 'metal_fragments'),
            'hq.metal.ore' => Statistics::getImage($images, 'hq.metal.ore'),
            'gunpowder' => Statistics::getImage($images, 'gunpowder'),
            'low_grade_fuel' => Statistics::getImage($images, 'low_grade_fuel'),
            'sulfur' => Statistics::getImage($images, 'sulfur'),
            'charcoal' => Statistics::getImage($images, 'charcoal'),
        ];

        return [
            'farm' => $farm,
            'hunters' => $hunters,
            'ferm' => $ferm,
            'fishing' => $fishing,
            'craftable_images' => $craftableImages,
        ];
    }

    public function actionPlayerNew($serverTag, $steamId)
    {
        $server = Servers::find()->where(['tag' => $serverTag])->one();
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        $periodAll = \Yii::$app->request->get('period') === 'all';
        $requestWipe = \Yii::$app->request->get('wipe');
        $wipe = $periodAll ? null : (($requestWipe !== null && $requestWipe !== '') ? $requestWipe : $server->currentWipe());

        // Кэшируем статистику игрока на 5 минут (в ключе заменяем / на _ для совместимости с бэкендами кэша)
        // _v6: team_members вынесены в отдельный endpoint player-team (для бэйджа только team_members_count)
        $wipeKey = $periodAll ? 'all' : str_replace('/', '_', (string)($wipe ?? 'current'));
        $cacheKey = 'api_stats_player_' . $serverTag . '_' . $steamId . '_' . $wipeKey . '_v6';
        $cached = Yii::$app->cache->get($cacheKey);
        
        if ($cached === false) {
            $playerStats = $periodAll
                ? Statistics::getPlayerStatsAllTime($steamId)
                : Statistics::getPlayerStats($server, $steamId, $wipe);

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
            $wipesCount = $periodAll
                ? (int) Statistics::find()
                    ->select([new \yii\db\Expression('COUNT(DISTINCT CONCAT(COALESCE(server_tag,\'\'), \'|\', COALESCE(wipe,\'\')))')])
                    ->andWhere(['steam_id' => $steamId])
                    ->scalar()
                : (int) Statistics::find()
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
            $serverTags = array_unique(array_filter(array_column($wipesActivityRows, 'server_tag')));
            $serverNamesByTag = [];
            if (!empty($serverTags)) {
                $serversList = Servers::find()
                    ->select(['tag', 'monitoring_name'])
                    ->andWhere(['tag' => $serverTags])
                    ->asArray()
                    ->all();
                foreach ($serversList as $s) {
                    $serverNamesByTag[$s['tag']] = $s['monitoring_name'] ?? $s['tag'];
                }
            }
            $wipesActivity = [];
            foreach ($wipesActivityRows as $row) {
                $playtime = is_numeric($row['value']) ? (int) $row['value'] : 0;
                $tag = $row['server_tag'] ?? '';
                $wipesActivity[] = [
                    'wipe' => $row['wipe'],
                    'server_tag' => $tag,
                    'server_name' => $serverNamesByTag[$tag] ?? $tag,
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

            $killWeapons = $periodAll
                ? Kills::find()
                    ->select(['weapon', 'COUNT(*) as count'])
                    ->andWhere(['steam_id' => $steamId])
                    ->andWhere('weapon IS NOT NULL')
                    ->asArray()
                    ->groupBy('weapon')
                    ->orderBy(['count' => SORT_DESC])
                    ->all()
                : Kills::find()
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
                    'image_large' => Statistics::getImageLarge($images, $item['weapon']),
                    'count' => (int) $item['count'],
                ];
            }

            $farmItems = [
                ['name' => \Yii::t('common', 'Серная руда'), 'key' => 'sulfur.ore', 'score' => 1],
                ['name' => \Yii::t('common', 'Железная руда'), 'key' => 'metal.ore', 'score' => 0.5],
                ['name' => \Yii::t('common', 'Камни'), 'key' => 'stones', 'score' => 0.3],
                ['name' => \Yii::t('common', 'Дерево'), 'key' => 'wood', 'score' => 0.05],
                ['name' => \Yii::t('common', 'Дизельная бочка'), 'key' => 'diesel_barrel', 'score' => 0],
                ['name' => \Yii::t('common', 'Обломки костей'), 'key' => 'bones', 'param' => 'bone.fragments', 'image_key' => 'bone.fragments', 'score' => 0],
                ['name' => \Yii::t('common', 'Животный жир'), 'key' => 'animal_fat', 'param' => 'fat.animal', 'image_key' => 'fat.animal', 'score' => 0],
                ['name' => \Yii::t('common', 'Кожа'), 'key' => 'leather', 'score' => 0],
                ['name' => \Yii::t('common', 'Скрап'), 'key' => 'scrap', 'score' => 0],
            ];
            $farm = [];
            foreach ($farmItems as $item) {
                $statKey = $item['param'] ?? $item['key'];
                $row = Statistics::getFarmItem($images, $names, $playerStats, $statKey, $item['name'], $item['score']);
                $imageKey = $item['image_key'] ?? $item['key'];
                $count = (int) $row['count'];
                $farm[] = [
                    'key' => $item['key'],
                    'name' => $row['name'],
                    'image' => Statistics::getImage($images, $imageKey),
                    'count' => $count,
                    'score' => (float) $row['score'],
                ];
            }

            // Чаепитие — как в _player_stats_tea.php: ключи mod_*tea* из статистики, getFoodItem (в БД ключ mod_*)
            $tea = [];
            foreach ($playerStats as $statKey => $unused) {
                if (strpos($statKey, 'mod_') !== 0 || strpos($statKey, 'tea') === false) {
                    continue;
                }
                $key = str_replace('mod_', '', $statKey);
                $row = Statistics::getFoodItem($images, $names, $playerStats, $key);
                $tea[] = [
                    'key' => $row['key'],
                    'name' => $row['name'],
                    'image' => $row['image'],
                    'count' => (int) $row['count'],
                    'score' => 0,
                ];
            }
            usort($tea, function ($a, $b) {
                return $b['count'] - $a['count'];
            });
            $tea = array_slice($tea, 0, 10);

            // Пироги — как в _player_stats_pie.php: ключи mod_*pie* из статистики, getFoodItem
            $pie = [];
            foreach ($playerStats as $statKey => $unused) {
                if (strpos($statKey, 'mod_') !== 0 || strpos($statKey, 'pie') === false) {
                    continue;
                }
                $key = str_replace('mod_', '', $statKey);
                $row = Statistics::getFoodItem($images, $names, $playerStats, $key);
                $pie[] = [
                    'key' => $row['key'],
                    'name' => $row['name'],
                    'image' => $row['image'],
                    'count' => (int) $row['count'],
                    'score' => 0,
                ];
            }
            usort($pie, function ($a, $b) {
                return $b['count'] - $a['count'];
            });
            $pie = array_slice($pie, 0, 10);

            // Любимая еда — как в _player_stats_food.php: mod_* кроме tea, pie, largemedkit
            $food = [];
            foreach ($playerStats as $statKey => $unused) {
                if (strpos($statKey, 'mod_') !== 0) {
                    continue;
                }
                if (strpos($statKey, 'pie') !== false) {
                    continue;
                }
                $key = str_replace('mod_', '', $statKey);
                if (strpos($key, 'tea') !== false) {
                    continue;
                }
                if (strpos($key, 'largemedkit') !== false) {
                    continue;
                }
                $row = Statistics::getFoodItem($images, $names, $playerStats, $key);
                $food[] = [
                    'key' => $row['key'],
                    'name' => $row['name'],
                    'image' => $row['image'],
                    'count' => (int) $row['count'],
                    'score' => 0,
                ];
            }
            usort($food, function ($a, $b) {
                return $b['count'] - $a['count'];
            });
            $food = array_slice($food, 0, 10);

            // Медицина (для вкладки Убийства) — загружается через endpoint player-kills

            // Охота — животные; картинки как в frontend/views/stats/_player_stats_hunter.php (images/hunters/*.png) через S3
            $hunterItems = [
                ['key' => 'boar', 'name' => \Yii::t('common', 'Кабаны'), 'image_path' => 'images/hunters/Boar.png'],
                ['key' => 'horse', 'name' => \Yii::t('common', 'Лошади'), 'image_path' => 'images/hunters/Horse.png'],
                ['key' => 'wolf', 'name' => \Yii::t('common', 'Волки'), 'param' => ['wolf', 'wolf2', 'skull.wolf'], 'image_path' => 'images/hunters/Wolf.png'],
                ['key' => 'bear', 'name' => \Yii::t('common', 'Медведи'), 'param' => ['bear', 'polarbear'], 'image_path' => 'images/hunters/bear.png'],
                ['key' => 'deer', 'name' => \Yii::t('common', 'Олени'), 'param' => ['deer', 'stag'], 'image_path' => 'images/hunters/Stag.png'],
                ['key' => 'chicken', 'name' => \Yii::t('common', 'Курицы'), 'image_path' => 'images/hunters/Chicken.png'],
                ['key' => 'simpleshark', 'name' => \Yii::t('common', 'Акулы'), 'image_path' => 'images/hunters/shark2.png'],
                ['key' => 'panther', 'name' => \Yii::t('common', 'Пантеры'), 'image_path' => 'images/hunters/panther.png'],
                ['key' => 'crocodile', 'name' => \Yii::t('common', 'Крокодилы'), 'image_path' => 'images/hunters/crocodile.png'],
                ['key' => 'tiger', 'name' => \Yii::t('common', 'Тигры'), 'image_path' => 'images/hunters/tiger.png'],
                ['key' => 'snake', 'name' => \Yii::t('common', 'Змеи'), 'param' => ['snake.entity'], 'product_image_key' => 'snake.entity'],
            ];
            $deathsByAnimal = Kills::getDeathsByAnimalCounts($user, $periodAll ? null : $server, $wipe, $periodAll);
            $hunters = [];
            foreach ($hunterItems as $item) {
                $count = 0;
                if (!empty($item['param'])) {
                    foreach ((array) $item['param'] as $p) {
                        $count += (int) Statistics::getParam($playerStats, $p);
                    }
                } else {
                    $count = (int) Statistics::getParam($playerStats, $item['key']);
                }
                $killedPlayer = 0;
                if (!empty($item['param'])) {
                    foreach ((array) $item['param'] as $p) {
                        $killedPlayer += (int) (isset($deathsByAnimal[$p]) ? $deathsByAnimal[$p] : 0);
                    }
                } else {
                    $killedPlayer = (int) (isset($deathsByAnimal[$item['key']]) ? $deathsByAnimal[$item['key']] : 0);
                }
                $imageUrl = !empty($item['product_image_key'])
                    ? Statistics::getImage($images, $item['product_image_key'])
                    : self::getStaticImageUrl($item['image_path']);
                $hunters[] = [
                    'key' => $item['key'],
                    'name' => $item['name'],
                    'image' => $imageUrl,
                    'count' => $count,
                    'killed_player' => $killedPlayer,
                    'score' => 0,
                ];
            }

            // Фермерство — собранные культуры (count x score)
            $fermItems = [
                ['name' => \Yii::t('common', 'Ткань'), 'key' => 'gathered_cloth', 'score' => 0.05],
                ['name' => \Yii::t('common', 'Кукуруза'), 'key' => 'gathered_corn', 'score' => 0.3],
                ['name' => \Yii::t('common', 'Картофель'), 'key' => 'gathered_potato', 'score' => 0.4],
                ['name' => \Yii::t('common', 'Тыква'), 'key' => 'gathered_pumpkin', 'score' => 0.5],
                ['name' => \Yii::t('common', 'Синие ягоды'), 'key' => 'gathered_blue.berry', 'score' => 0.5],
                ['name' => \Yii::t('common', 'Желтые ягоды'), 'key' => 'gathered_yellow.berry', 'score' => 0.5],
                ['name' => \Yii::t('common', 'Красные ягоды'), 'key' => 'gathered_red.berry', 'score' => 0.5],
                ['name' => \Yii::t('common', 'Белые ягоды'), 'key' => 'gathered_white.berry', 'score' => 0.5],
                ['name' => \Yii::t('common', 'Зеленые ягоды'), 'key' => 'gathered_green.berry', 'score' => 0.5],
                ['name' => \Yii::t('common', 'Черные ягоды'), 'key' => 'gathered_black.berry', 'score' => 1],
                ['name' => \Yii::t('common', 'Орхидея'), 'key' => 'gathered_orchid', 'score' => 0.3],
                ['name' => \Yii::t('common', 'Розы'), 'key' => 'gathered_rose', 'score' => 0.3],
                ['name' => \Yii::t('common', 'Подсолнух'), 'key' => 'gathered_sunflower', 'score' => 0.3],
                ['name' => \Yii::t('common', 'Пшеница'), 'key' => 'gathered_wheat', 'score' => 0.3],
            ];
            // Фермерство — ключи как в getFermItem: gathered_cloth, gathered_blue.berry и т.д.; картинки из $images (S3)
            $ferm = [];
            foreach ($fermItems as $item) {
                $row = Statistics::getFermItem($images, $playerStats, $item['key'], $item['name'], $item['score']);
                $ferm[] = [
                    'key' => $item['key'],
                    'name' => $row['name'],
                    'image' => $row['image'],
                    'count' => (int) $row['count'],
                    'score' => (float) $item['score'],
                ];
            }

            // Рыболовство — рыба (count x множитель)
            $fishingItems = [
                ['name' => \Yii::t('common', 'Акула'), 'key' => 'f_fish.smallshark', 'score' => 45],
                ['name' => \Yii::t('common', 'Большеголов'), 'key' => 'f_fish.orangeroughy', 'score' => 37],
                ['name' => \Yii::t('common', 'Сом'), 'key' => 'f_fish.catfish', 'score' => 32],
                ['name' => \Yii::t('common', 'Окунь'), 'key' => 'f_fish.yellowperch', 'score' => 25],
                ['name' => \Yii::t('common', 'Лосось'), 'key' => 'f_fish.salmon', 'score' => 22],
                ['name' => \Yii::t('common', 'Форель'), 'key' => 'f_fish.troutsmall', 'score' => 15],
                ['name' => \Yii::t('common', 'Анчоус'), 'key' => 'f_fish.anchovy', 'score' => 10],
                ['name' => \Yii::t('common', 'Сельдь'), 'key' => 'f_fish.herring', 'score' => 10],
                ['name' => \Yii::t('common', 'Сардина'), 'key' => 'f_fish.sardine', 'score' => 10],
            ];
            // Рыболовство — ключи как в getFishItem: f_fish.anchovy и т.д.; картинки из $images (S3)
            $fishing = [];
            foreach ($fishingItems as $item) {
                $row = Statistics::getFishItem($images, $playerStats, $item['key'], $item['name'], $item['score']);
                $fishing[] = [
                    'key' => $item['key'],
                    'name' => $row['name'],
                    'image' => $row['image'],
                    'count' => (int) $row['count'],
                    'score' => (float) $item['score'],
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

            // Команда игрока (как в frontend/views/widgets/teams.twig)
            $teamMembers = [];
            $teamHidden = false;
            if ($user->hasHideTeam()) {
                $teamHidden = true;
            } else {
                try {
                    $teamMembers = TeamsModel::getTeamList($server->id, $user->id, $wipe);
                } catch (\Exception $e) {
                    $teamMembers = [];
                }
            }

            // Стройка: апгрейд блоков (ExpertStatistics OnStructureUpgrade) — для вкладки «Общая информация»
            $buildingKeys = [
                'upgrade_wood' => \Yii::t('common', 'Дерево'),
                'upgrade_stone' => \Yii::t('common', 'Камень'),
                'upgrade_metal' => \Yii::t('common', 'Металл'),
                'upgrade_toptier' => \Yii::t('common', 'МВК'),
            ];
            $building = [];
            foreach ($buildingKeys as $key => $name) {
                $count = (int) Statistics::getParam($playerStats, $key);
                $building[] = [
                    'key' => $key,
                    'name' => $name,
                    'image' => Statistics::getImage($images, $key),
                    'count' => $count,
                ];
            }

            // История убийств и медицинские предметы — отдельный endpoint player-kills при открытии вкладки Убийства
            $killsForApi = [];
            $medical = [];

            $cached = [
                'player' => [
                    'steam_id' => $steamId,
                    'server_tag' => $serverTag,
                    'server_name' => $server->monitoring_name ?? $serverTag,
                    'wipe' => $wipe,
                    'username' => $user->username,
                    'avatar' => $user->getAvatar(),
                    'has_vip' => $user->hasVip(),
                    'avatar_frame_id' => null,
                    'avatar_frame_url' => null,
                    'display_status' => $user->getDisplayStatus(),
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
                    'craftable_images' => [
                        'metal_fragments' => Statistics::getImage($images, 'metal_fragments'),
                        'hq.metal.ore' => Statistics::getImage($images, 'hq.metal.ore'),
                        'gunpowder' => Statistics::getImage($images, 'gunpowder'),
                        'low_grade_fuel' => Statistics::getImage($images, 'low_grade_fuel'),
                        'sulfur' => Statistics::getImage($images, 'sulfur'),
                        'charcoal' => Statistics::getImage($images, 'charcoal'),
                    ],
                    'tea' => $tea,
                    'pie' => $pie,
                    'food' => $food,
                    'medical' => $medical,
                    'hunters' => $hunters,
                    'ferm' => $ferm,
                    'fishing' => $fishing,
                    'awards' => $awards,
                    'awards_stats' => [
                        'completed' => $awardsCompleted,
                        'total' => count($awards),
                    ],
                    'wipes_activity' => $wipesActivity,
                    'kills' => $killsForApi,
                    'team_members_count' => count($teamMembers),
                    'team_hidden' => $teamHidden,
                    'building' => $building,
                ],
            ];

            // Сохраняем в кэш на 5 минут
            Yii::$app->cache->set($cacheKey, $cached, 300);
        }

        // Всегда подставляем актуальный текущий вайп сервера для отображения на фронте
        $cached['player']['current_wipe'] = $server->currentWipe();

        // Рамка и VIP — актуальные из БД поверх кеша (VIP мог истечь; без VIP рамку не отдаём, хотя в БД может остаться avatar_frame).
        $userNow = User::findOne(['steam_id' => $steamId]);
        $cached['player']['avatar_frame_id'] = null;
        $cached['player']['avatar_frame_url'] = null;
        if ($userNow) {
            $cached['player']['has_vip'] = $userNow->hasVip();
            $frameUrl = $userNow->getAvatarFrameImageUrl();
            if ($frameUrl !== null) {
                $fid = (int)($userNow->avatar_frame ?? 0);
                $cached['player']['avatar_frame_id'] = $fid > 0 ? $fid : null;
                $cached['player']['avatar_frame_url'] = $frameUrl;
            }
        }

        // Скрытая от других команда — для владельца профиля показываем счётчик и не помечаем как скрыто (кэш остаётся «публичным»)
        if ($this->isViewerProfileOwner($steamId) && $userNow && $userNow->hasHideTeam()) {
            try {
                $teamMembersOwner = TeamsModel::getTeamList($server->id, $userNow->id, $wipe);
            } catch (\Exception $e) {
                $teamMembersOwner = [];
            }
            $cached['player']['team_members_count'] = count($teamMembersOwner);
            $cached['player']['team_hidden'] = false;
        }

        $cached['player']['team_hidden_from_others'] = $this->isViewerProfileOwner($steamId) && $userNow && $userNow->hasHideTeam();

        return $this->successResponse($cached);
    }

    /**
     * Данные для вкладки «Ресурсы и добыча» (farm, hunters, ferm, fishing, craftable_images).
     * Вызывается только при открытии вкладки для уменьшения нагрузки на основной запрос профиля.
     *
     * @OA\Get(
     *     path="/v1/stats/player-resources",
     *     operationId="getPlayerResources",
     *     tags={"Stats"},
     *     summary="Ресурсы и добыча игрока",
     *     @OA\Parameter(name="serverTag", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="steamId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="wipe", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="period", in="query", required=false, description="all = за всё время", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="farm, hunters, ferm, fishing, craftable_images")
     * )
     */
    public function actionPlayerResources()
    {
        $serverTag = \Yii::$app->request->get('serverTag');
        $steamId = \Yii::$app->request->get('steamId');
        if (empty($serverTag) || empty($steamId)) {
            return $this->errorResponse('INVALID_PARAMS', 'Требуются serverTag и steamId', [], 400);
        }

        $server = Servers::find()->where(['tag' => $serverTag])->one();
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        $periodAll = \Yii::$app->request->get('period') === 'all';
        $requestWipe = \Yii::$app->request->get('wipe');
        $wipe = $periodAll ? null : (($requestWipe !== null && $requestWipe !== '') ? $requestWipe : $server->currentWipe());

        $wipeKey = $periodAll ? 'all' : str_replace('/', '_', (string)($wipe ?? 'current'));
        $cacheKey = 'api_stats_player_resources_' . $serverTag . '_' . $steamId . '_' . $wipeKey . '_v1';
        $cached = Yii::$app->cache->get($cacheKey);

        if ($cached === false) {
            $playerStats = $periodAll
                ? Statistics::getPlayerStatsAllTime($steamId)
                : Statistics::getPlayerStats($server, $steamId, $wipe);

            $user = User::findBySteamId($steamId, false, 'stats');
            if (!$user) {
                throw new NotFoundHttpException('Игрок не найден');
            }

            $cached = $this->buildPlayerResourcesData($server, $user, $wipe, $periodAll, $playerStats);
            Yii::$app->cache->set($cacheKey, $cached, 300);
        }

        return $this->successResponse($cached);
    }

    /**
     * Данные для вкладки «Убийства»: история убийств (последние 30) и медицинские предметы.
     * Вызывается только при открытии вкладки.
     *
     * @param \common\models\servers\Servers $server
     * @param \common\models\user\User $user
     * @param string|null $wipe
     * @param bool $periodAll
     * @param array $playerStats результат Statistics::getPlayerStats / getPlayerStatsAllTime
     * @return array{kills: array, medical: array}
     */
    private function buildPlayerKillsData($server, $user, $wipe, $periodAll, $playerStats)
    {
        $killsList = $periodAll
            ? Kills::getKillsAllTime($user, 30)
            : Kills::getKills($server, $user, 30, $wipe);
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
                'avatar' => $k['avatar'] ?? null,
                'dead_name' => $k['dead_name'] ?? null,
                'dead_link' => $k['dead_link'] ?? null,
                'dead_avatar' => $k['dead_avatar'] ?? null,
                'deadLink' => $k['dead_link'] ?? null,
                'signs' => $k['signs'] ?? null,
                'wears' => $k['wears'] ?? null,
                'bot' => !empty($k['bot']),
                'animal' => $k['animal'] ?? null,
                'animal2' => $k['animal2'] ?? null,
                'created_at' => $k['created_at'] ?? '',
            ];
        }, $killsList);

        $images = Statistics::productsImages();
        $healItems = [
            ['name' => \Yii::t('common', 'Большая аптечка'), 'key' => 'first_aid_kit', 'image_key' => 'largemedkit'],
            ['name' => \Yii::t('common', 'Медицинский шприц'), 'key' => 'syringe', 'image_key' => 'syringe'],
            ['name' => \Yii::t('common', 'Бинт'), 'key' => 'bandage', 'image_key' => 'bandage'],
        ];
        $medical = [];
        foreach ($healItems as $item) {
            $count = (int) Statistics::getParam($playerStats, $item['key']);
            $imageKey = $item['image_key'] ?? $item['key'];
            $medical[] = [
                'key' => $item['key'],
                'name' => $item['name'],
                'image' => Statistics::getImage($images, $imageKey),
                'count' => $count,
                'score' => 0,
            ];
        }

        return [
            'kills' => $killsForApi,
            'medical' => $medical,
        ];
    }

    /**
     * @param \common\models\servers\Servers $server
     * @param \common\models\user\User $user
     * @param string|null $wipe
     * @param bool $viewerIsProfileOwner владелец профиля видит команду даже при VIP «скрыть тимейтов»
     * @return array{team_members: array, team_hidden: bool}
     */
    private function buildPlayerTeamData($server, $user, $wipe, $viewerIsProfileOwner = false)
    {
        $hideFromOthers = $user->hasHideTeam();
        $teamHidden = $hideFromOthers && !$viewerIsProfileOwner;
        $teamMembers = [];
        if (!$hideFromOthers || $viewerIsProfileOwner) {
            try {
                $teamMembers = TeamsModel::getTeamList($server->id, $user->id, $wipe);
            } catch (\Exception $e) {
                $teamMembers = [];
            }
        }
        // team_hidden_from_others: только для владельца (JWT) — команда скрыта от других, сам список видит
        return [
            'team_members' => $teamMembers,
            'team_hidden' => $teamHidden,
            'team_hidden_from_others' => $viewerIsProfileOwner && $hideFromOthers,
        ];
    }

    /**
     * Данные для вкладки «Убийства» (kills, medical).
     * Вызывается только при открытии вкладки.
     *
     * @OA\Get(
     *     path="/v1/stats/player-kills",
     *     operationId="getPlayerKills",
     *     tags={"Stats"},
     *     summary="История убийств и медицина игрока",
     *     @OA\Parameter(name="serverTag", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="steamId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="wipe", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="period", in="query", required=false, description="all = за всё время", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="kills, medical")
     * )
     */
    public function actionPlayerKills()
    {
        $serverTag = \Yii::$app->request->get('serverTag');
        $steamId = \Yii::$app->request->get('steamId');
        if (empty($serverTag) || empty($steamId)) {
            return $this->errorResponse('INVALID_PARAMS', 'Требуются serverTag и steamId', [], 400);
        }

        $server = Servers::find()->where(['tag' => $serverTag])->one();
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        $periodAll = \Yii::$app->request->get('period') === 'all';
        $requestWipe = \Yii::$app->request->get('wipe');
        $wipe = $periodAll ? null : (($requestWipe !== null && $requestWipe !== '') ? $requestWipe : $server->currentWipe());

        $wipeKey = $periodAll ? 'all' : str_replace('/', '_', (string)($wipe ?? 'current'));
        $cacheKey = 'api_stats_player_kills_' . $serverTag . '_' . $steamId . '_' . $wipeKey . '_v1';
        $cached = Yii::$app->cache->get($cacheKey);

        if ($cached === false) {
            $playerStats = $periodAll
                ? Statistics::getPlayerStatsAllTime($steamId)
                : Statistics::getPlayerStats($server, $steamId, $wipe);

            $user = User::findBySteamId($steamId, false, 'stats');
            if (!$user) {
                throw new NotFoundHttpException('Игрок не найден');
            }

            $cached = $this->buildPlayerKillsData($server, $user, $wipe, $periodAll, $playerStats);
            Yii::$app->cache->set($cacheKey, $cached, 300);
        }

        return $this->successResponse($cached);
    }

    /**
     * Данные для вкладки «Тимейты» (team_members, team_hidden).
     * Вызывается только при открытии вкладки.
     *
     * @OA\Get(
     *     path="/v1/stats/player-team",
     *     operationId="getPlayerTeam",
     *     tags={"Stats"},
     *     summary="Команда игрока (тимейты)",
     *     @OA\Parameter(name="serverTag", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="steamId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="wipe", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="period", in="query", required=false, description="all = за всё время", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="team_members, team_hidden")
     * )
     */
    public function actionPlayerTeam()
    {
        $serverTag = \Yii::$app->request->get('serverTag');
        $steamId = \Yii::$app->request->get('steamId');
        if (empty($serverTag) || empty($steamId)) {
            return $this->errorResponse('INVALID_PARAMS', 'Требуются serverTag и steamId', [], 400);
        }

        $server = Servers::find()->where(['tag' => $serverTag])->one();
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        $periodAll = \Yii::$app->request->get('period') === 'all';
        $requestWipe = \Yii::$app->request->get('wipe');
        $wipe = $periodAll ? null : (($requestWipe !== null && $requestWipe !== '') ? $requestWipe : $server->currentWipe());

        $wipeKey = $periodAll ? 'all' : str_replace('/', '_', (string)($wipe ?? 'current'));
        $cacheKey = 'api_stats_player_team_' . $serverTag . '_' . $steamId . '_' . $wipeKey . '_v1';
        $cached = Yii::$app->cache->get($cacheKey);

        if ($cached === false) {
            $user = User::findBySteamId($steamId, false, 'stats');
            if (!$user) {
                throw new NotFoundHttpException('Игрок не найден');
            }
            // В кэше только публичный ответ; иначе чужой запрос после владельца получил бы список тимейтов
            $cached = $this->buildPlayerTeamData($server, $user, $wipe, false);
            Yii::$app->cache->set($cacheKey, $cached, 300);
        }

        if ($this->isViewerProfileOwner($steamId)) {
            $user = User::findBySteamId($steamId, false, 'stats');
            if ($user && $user->hasHideTeam()) {
                $cached = $this->buildPlayerTeamData($server, $user, $wipe, true);
            }
        }

        return $this->successResponse($cached);
    }

    /**
     * Данные для вкладки «Лут и РТ»: лут, карты доступа, чертежи, стройка.
     * Вызывается только при открытии вкладки.
     *
     * @OA\Get(
     *     path="/v1/stats/player-loot-crafts",
     *     operationId="getPlayerLootCrafts",
     *     tags={"Stats"},
     *     summary="Лут и крафты игрока",
     *     @OA\Parameter(name="serverTag", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="steamId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="wipe", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="period", in="query", required=false, description="all = за всё время", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="loot, access_cards, blueprints")
     * )
     */
    public function actionPlayerLootCrafts()
    {
        $serverTag = \Yii::$app->request->get('serverTag');
        $steamId = \Yii::$app->request->get('steamId');
        if (empty($serverTag) || empty($steamId)) {
            return $this->errorResponse('INVALID_PARAMS', 'Требуются serverTag и steamId', [], 400);
        }

        $server = Servers::find()->where(['tag' => $serverTag])->one();
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }

        $periodAll = \Yii::$app->request->get('period') === 'all';
        $requestWipe = \Yii::$app->request->get('wipe');
        $wipe = $periodAll ? null : (($requestWipe !== null && $requestWipe !== '') ? $requestWipe : $server->currentWipe());

        $wipeKey = $periodAll ? 'all' : str_replace('/', '_', (string)($wipe ?? 'current'));
        $cacheKey = 'api_stats_player_loot_crafts_' . $serverTag . '_' . $steamId . '_' . $wipeKey . '_v1';
        $cached = Yii::$app->cache->get($cacheKey);

        if ($cached === false) {
            $playerStats = $periodAll
                ? Statistics::getPlayerStatsAllTime($steamId)
                : Statistics::getPlayerStats($server, $steamId, $wipe);

            $user = User::findBySteamId($steamId, false, 'stats');
            if (!$user) {
                throw new NotFoundHttpException('Игрок не найден');
            }

            $cached = $this->buildPlayerLootCraftsData($playerStats);
            Yii::$app->cache->set($cacheKey, $cached, 300);
        }

        return $this->successResponse($cached);
    }

    /**
     * Собирает данные для вкладки «Лут и РТ»: лут, карты доступа, чертежи, стройка.
     *
     * @param array $playerStats результат Statistics::getPlayerStats / getPlayerStatsAllTime
     * @return array{loot: array, access_cards: array, blueprints: array}
     */
    private function buildPlayerLootCraftsData($playerStats)
    {
        $images = Statistics::productsImages();
        $names = Statistics::productsNames();

        // Лут: ящики, бочки, аирдроп, танки, вертолёты (карты доступа — в access_cards)
        $lootKeys = [
            'codelockedhackablecrate_oilrig' => \Yii::t('common', 'Крейт на нефтевышке'),
            'codelockedhackablecrate' => \Yii::t('common', 'Крейт'),
            'crate_elite' => \Yii::t('common', 'Элитный ящик'),
            'crate_normal' => \Yii::t('common', 'Армейский ящик'),
            'crate_underwater_advanced' => \Yii::t('common', 'Подводный ящик (продвинутый)'),
            'crate_underwater_basic' => \Yii::t('common', 'Подводный ящик (базовый)'),
            'supply_drop' => \Yii::t('common', 'Аирдроп'),
            'barrel' => \Yii::t('common', 'Разбито бочек'),
            'crate_open' => \Yii::t('common', 'Обычный ящик'),
            'bradleys' => \Yii::t('common', 'Взорванные танки'),
            'helicopters' => \Yii::t('common', 'Патрульные вертолёты'),
        ];

        $loot = [];
        foreach ($lootKeys as $key => $name) {
            $count = (int) Statistics::getParam($playerStats, $key);
            $loot[] = [
                'key' => $key,
                'name' => $name,
                'image' => Statistics::getImageLarge($images, $key),
                'image_large' => Statistics::getImageLarge($images, $key),
                'count' => $count,
            ];
        }

        // Карты доступа: три позиции в порядке Зелёная → Синяя → Красная (обратный порядок)
        // Синяя (card_level_1) и Зелёная (card_level_2) в базе картинок перепутаны — подставляем изображения наоборот
        $accessCardKeys = [
            ['key' => 'card_level_1', 'name' => \Yii::t('common', 'Зелёная карта доступа'), 'imageKey' => 'card_level_1'],
            ['key' => 'card_level_2', 'name' => \Yii::t('common', 'Синяя карта доступа'), 'imageKey' => 'card_level_2'],
            ['key' => 'card_level_3', 'name' => \Yii::t('common', 'Красная карта доступа'), 'imageKey' => 'card_level_3'],
        ];
        $access_cards = [];
        foreach ($accessCardKeys as $item) {
            $count = (int) Statistics::getParam($playerStats, $item['key']);
            $access_cards[] = [
                'key' => $item['key'],
                'name' => $item['name'],
                'image' => Statistics::getImageLarge($images, $item['imageKey']),
                'count' => $count,
            ];
        }

        // Чертежи: фрагменты из лута ящиков (ExpertStatistics OnLootEntity)
        $blueprintKeys = [
            'basicblueprintfragment' => \Yii::t('common', 'Фрагмент простого чертежа'),
            'advancedblueprintfragment' => \Yii::t('common', 'Фрагмент продвинутого чертежа'),
        ];
        $blueprints = [];
        foreach ($blueprintKeys as $key => $name) {
            $count = (int) Statistics::getParam($playerStats, $key);
            if ($count > 0) {
                $blueprints[] = [
                    'key' => $key,
                    'name' => $name,
                    'image' => Statistics::getImageLarge($images, $key),
                    'count' => $count,
                ];
            }
        }

        return [
            'loot' => $loot,
            'access_cards' => $access_cards,
            'blueprints' => $blueprints,
        ];
    }

    /**
     * Дуэли игрока: сводка по противникам (убийства в обе стороны) по всем серверам.
     * Фильтр только по дате вайпа.
     *
     * @OA\Get(
     *     path="/v1/stats/duels",
     *     operationId="getPlayerDuels",
     *     tags={"Stats"},
     *     summary="Дуэли игрока по вайпу (без привязки к серверу)",
     *     @OA\Parameter(name="steamId", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="wipe", in="query", required=false, description="Пусто = за все время", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Список дуэлей")
     * )
     */
    public function actionDuels()
    {
        $steamId = \Yii::$app->request->get('steamId');
        if (empty($steamId)) {
            return $this->errorResponse('INVALID_PARAMS', 'Требуется steamId', [], 400);
        }
        $wipeParam = \Yii::$app->request->get('wipe');
        $wipe = ($wipeParam !== null && $wipeParam !== '') ? $wipeParam : null;

        $duels = Kills::getDuels($steamId, $wipe);

        return $this->successResponse([
            'steam_id' => $steamId,
            'wipe' => $wipe,
            'duels' => $duels,
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
        $server = Servers::find()->where(['tag' => $serverTag])->one();
        if (!$server) {
            throw new NotFoundHttpException('Сервер не найден');
        }
        if ($wipe === null) {
            $wipe = $server->currentWipe();
        }
        $cacheKey = StatsCacheHelper::cacheKey($server->tag, $wipe);
        $cached = Yii::$app->cache->get($cacheKey);
        if ($cached !== false && isset($cached['tops'])) {
            $tops = $cached['tops'];
        } else {
            $tops = StatsCacheHelper::getTopsFormatted($server, $wipe);
            if ($cached === false) {
                $payload = StatsCacheHelper::buildPayload($server, $wipe);
                Yii::$app->cache->set($cacheKey, $payload, StatsCacheHelper::CACHE_TTL);
            }
        }

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

}

