<?php

namespace api\controllers\v1;

use Yii;
use common\models\building\Building;
use common\models\building\BuildingLike;
use common\models\building\BuildingImage;
use common\models\building\BuildingResident;
use common\models\servers\Servers;
use common\models\box\DropImage;
use common\models\user\UserRaid;
use yii\data\ActiveDataProvider;
use yii\web\UploadedFile;
use yii\imagine\Image;
use Imagine\Image\Box;
use Imagine\Image\Point;
use yii\validators\ImageValidator;
use api\components\jwt\JwtAuthFilter;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с постройками
 *
 * @package api\controllers\v1
 * @OA\Tag(name="Buildings")
 */
class BuildingsController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // JWT авторизация требуется для постановки лайка, создания постройки и загрузки изображений
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'only' => ['like', 'create', 'upload-image'],
            'except' => ['index', 'view', 'likes', 'options'],
        ];

        return $behaviors;
    }

    /**
     * Получение списка построек с пагинацией
     *
     * @OA\Get(
     *     path="/v1/buildings",
     *     operationId="getBuildings",
     *     tags={"Buildings"},
     *     summary="Получить список построек",
     *     description="Возвращает список построек с фильтрацией и пагинацией",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Номер страницы",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Количество построек на странице",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="server_tag",
     *         in="query",
     *         description="Тег сервера для фильтрации",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Поиск по названию или описанию",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Поле для сортировки (created_at, likes, name)",
     *         required=false,
     *         @OA\Schema(type="string", default="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="order",
     *         in="query",
     *         description="Направление сортировки (asc, desc)",
     *         required=false,
     *         @OA\Schema(type="string", default="desc", enum={"asc", "desc"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список построек",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionIndex()
    {
        $request = Yii::$app->request;
        $page = (int)$request->get('page', 1);
        $limit = (int)$request->get('limit', 10);
        $serverTag = $request->get('server_tag');
        $search = $request->get('search');
        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');

        // Кэшируем только если нет фильтров (базовый список)
        $hasFilters = !empty($serverTag) || !empty($search);
        $cacheKey = null;
        $cachedData = null;
        
        if (!$hasFilters && $page === 1) {
            $cacheKey = 'api_buildings_list_' . $sort . '_' . $order;
            $cache = Yii::$app->cache;
            $cachedData = $cache->get($cacheKey);
        }

        if ($cachedData === false || $hasFilters || $page > 1) {
            $query = Building::find()
                ->alias('b')
                ->where(['b.status' => Building::STATUS_ACTIVE])
                ->with(['buildingImage', 'user', 'server']);

            // Фильтр по серверу
            if ($serverTag) {
                $query->andWhere(['b.server_tag' => $serverTag]);
            }

            // Поиск по названию или описанию
            if ($search) {
                $query->andWhere(['or',
                    ['like', 'b.name', $search],
                    ['like', 'b.description', $search]
                ]);
            }

            // Сортировка
            $allowedSorts = ['created_at', 'likes', 'name'];
            if (!in_array($sort, $allowedSorts)) {
                $sort = 'created_at';
            }
            $sortOrder = strtolower($order) === 'asc' ? SORT_ASC : SORT_DESC;
            $query->orderBy(["b.{$sort}" => $sortOrder]);

            $dataProvider = new ActiveDataProvider([
                'query' => $query,
                'pagination' => [
                    'page' => $page - 1, // ActiveDataProvider использует 0-based индексацию
                    'pageSize' => $limit,
                ],
            ]);

            // Получаем текущего пользователя (если авторизован)
            $currentUser = null;
            try {
                $currentUser = $this->getCurrentUser();
            } catch (\Exception $e) {
                // Пользователь не авторизован - это нормально для публичного списка
            }

            // Если пользователь авторизован, получаем все его лайки одним запросом
            $userLikedBuildingIds = [];
            if ($currentUser) {
                $userLikes = BuildingLike::find()
                    ->select('building_id')
                    ->where(['user_id' => $currentUser->id])
                    ->asArray()
                    ->all();
                $userLikedBuildingIds = array_column($userLikes, 'building_id');
            }

            $buildings = [];
            foreach ($dataProvider->getModels() as $building) {
                // Получаем первое изображение с S3 URL
                $imageUrl = null;
                $buildingImages = $building->buildingImage;
                if (!empty($buildingImages)) {
                    if (is_array($buildingImages) && count($buildingImages) > 0) {
                        $firstImage = reset($buildingImages);
                        if ($firstImage) {
                            $imageUrl = $firstImage->getPublicUrl();
                        }
                    } elseif (is_object($buildingImages)) {
                        $images = $buildingImages->all();
                        if (!empty($images)) {
                            $firstImage = reset($images);
                            if ($firstImage) {
                                $imageUrl = $firstImage->getPublicUrl();
                            }
                        }
                    }
                }

                // Проверяем, лайкнул ли текущий пользователь эту постройку
                $isLiked = $currentUser && in_array($building->id, $userLikedBuildingIds);

                $buildings[] = [
                    'id' => $building->id,
                    'name' => $building->name,
                    'description' => $building->description,
                    'location' => $building->location,
                    'image' => $imageUrl,
                    'likes' => $building->likes ?? 0,
                    'is_liked' => $isLiked,
                    'wipe' => $building->wipe,
                    'server' => $building->server ? [
                        'tag' => $building->server->tag,
                        'name' => $building->server->monitoring_name ?? $building->server->tag,
                    ] : null,
                    'user' => $building->user ? [
                        'id' => $building->user->id,
                        'username' => $building->user->username,
                        'steamId' => $building->user->steam_id,
                        'avatar' => $building->user->getAvatar(),
                    ] : null,
                    'createdAt' => $building->created_at,
                ];
            }

            $pagination = $dataProvider->getPagination();
            $totalPages = $pagination->getPageCount();

            $responseData = [
                'buildings' => $buildings,
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => $totalPages,
                    'totalCount' => $dataProvider->getTotalCount(),
                    'pageSize' => $limit,
                    'hasMore' => $page < $totalPages,
                ],
            ];

            // Сохраняем в кэш только базовый список (без фильтров, первая страница)
            if (!$hasFilters && $page === 1 && $cacheKey) {
                $cache->set($cacheKey, $responseData, 300); // 5 минут
            }

            return $this->successResponse($responseData);
        } else {
            // Используем кэшированные данные
            return $this->successResponse($cachedData);
        }
    }

    /**
     * Получение детальной информации о постройке
     *
     * @OA\Get(
     *     path="/v1/buildings/{id}",
     *     operationId="getBuilding",
     *     tags={"Buildings"},
     *     summary="Получить информацию о постройке",
     *     description="Возвращает детальную информацию о постройке",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID постройки",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Информация о постройке",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=404, description="Постройка не найдена")
     * )
     */
    public function actionView($id)
    {
        // Кэшируем детальную информацию о постройке на 10 минут
        $cacheKey = 'api_buildings_view_' . $id;
        $cache = Yii::$app->cache;
        $cachedBuilding = $cache->get($cacheKey);

        if ($cachedBuilding === false) {
            $building = Building::find()
                ->where(['id' => $id, 'status' => Building::STATUS_ACTIVE])
                ->with(['buildingImage', 'user', 'server', 'buildingResident.user'])
                ->one();

            if (!$building) {
                return $this->errorResponse('BUILDING_NOT_FOUND', 'Постройка не найдена', [], 404);
            }

            // Получаем все изображения с S3 URL
        $images = [];
        $buildingImages = $building->buildingImage;
        if (!empty($buildingImages)) {
            if (is_array($buildingImages)) {
                foreach ($buildingImages as $buildingImage) {
                    $images[] = $buildingImage->getPublicUrl();
                }
            } elseif (is_object($buildingImages)) {
                $imagesList = $buildingImages->all();
                foreach ($imagesList as $buildingImage) {
                    $images[] = $buildingImage->getPublicUrl();
                }
            }
        }

        // Получаем информацию о жителях
        $residents = [];
        $buildingResidents = $building->buildingResident;
        if (!empty($buildingResidents)) {
            if (is_array($buildingResidents)) {
                foreach ($buildingResidents as $resident) {
                    $user = $resident->user ?? null;
                    if ($user) {
                        $residents[] = [
                            'id' => $user->id,
                            'username' => $user->username,
                            'steamId' => $user->steam_id,
                            'avatar' => $user->getAvatar(),
                        ];
                    }
                }
            } elseif (is_object($buildingResidents)) {
                $residentsList = $buildingResidents->all();
                foreach ($residentsList as $resident) {
                    $user = $resident->user ?? null;
                    if ($user) {
                        $residents[] = [
                            'id' => $user->id,
                            'username' => $user->username,
                            'steamId' => $user->steam_id,
                            'avatar' => $user->getAvatar(),
                        ];
                    }
                }
            }
        }

        // Получаем информацию о рейдах постройки
        $raids = [];
        $raidCount = 0;
        $uniqueExplosives = [];

        if ($building->server && $building->location && $building->wipe) {
            // Получаем рейды по location, server_id и wipe
            $raidsQuery = UserRaid::find()
                ->where(['location' => $building->location])
                ->andWhere(['server_id' => $building->server->id])
                ->andWhere(['wipe' => $building->wipe])
                ->with('user')
                ->orderBy(['created_at' => SORT_DESC])
                ->limit(10)
                ->all();

            $raidCount = UserRaid::find()
                ->where(['location' => $building->location])
                ->andWhere(['server_id' => $building->server->id])
                ->andWhere(['wipe' => $building->wipe])
                ->count();

            // Подсчитываем уникальные взрывчатки
            $allExplosives = [];
            foreach ($raidsQuery as $raid) {
                if (!empty($raid->explosives)) {
                    $explosives = json_decode($raid->explosives, true);
                    if (is_array($explosives)) {
                        $allExplosives = array_merge($allExplosives, $explosives);
                    }
                }
            }
            $uniqueExplosives = array_values(array_unique($allExplosives));

            // Формируем массив рейдов для ответа
            foreach ($raidsQuery as $raid) {
                $raidData = [
                    'id' => $raid->id,
                    'type' => $raid->type,
                    'createdAt' => $raid->created_at,
                ];

                if ($raid->user) {
                    $raidData['user'] = [
                        'id' => $raid->user->id,
                        'username' => $raid->user->username,
                        'steamId' => $raid->user->steam_id,
                        'avatar' => $raid->user->getAvatar(),
                    ];
                }

                if (!empty($raid->explosives)) {
                    $explosives = json_decode($raid->explosives, true);
                    if (is_array($explosives)) {
                        $raidData['explosives'] = $explosives;
                    }
                }

                $raids[] = $raidData;
            }
        }

            $buildingData = [
                'id' => $building->id,
                'name' => $building->name,
                'description' => $building->description,
                'location' => $building->location,
                'images' => $images,
                'image' => !empty($images) ? $images[0] : null,
                'likes' => $building->likes ?? 0,
                'wipe' => $building->wipe,
                'server' => $building->server ? [
                    'tag' => $building->server->tag,
                    'name' => $building->server->monitoring_name ?? $building->server->tag,
                ] : null,
                'user' => $building->user ? [
                    'id' => $building->user->id,
                    'username' => $building->user->username,
                    'steamId' => $building->user->steam_id,
                    'avatar' => $building->user->getAvatar(),
                ] : null,
                'residents' => $residents,
                'createdAt' => $building->created_at,
                'raids' => [
                    'count' => $raidCount,
                    'list' => $raids,
                    'uniqueExplosives' => $uniqueExplosives,
                ],
            ];

            // Сохраняем в кэш на 10 минут
            $cache->set($cacheKey, $buildingData, 600);
        } else {
            $buildingData = $cachedBuilding;
        }

        return $this->successResponse($buildingData);
    }

    /**
     * Получение списка пользователей, поставивших лайк постройке
     *
     * @OA\Get(
     *     path="/v1/buildings/{id}/likes",
     *     operationId="getBuildingLikes",
     *     tags={"Buildings"},
     *     summary="Получить список пользователей, поставивших лайк",
     *     description="Возвращает список пользователей, которые поставили лайк постройке, с пагинацией",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID постройки",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Номер страницы",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Количество пользователей на странице",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список пользователей",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=404, description="Постройка не найдена")
     * )
     */
    public function actionLikes($id)
    {
        $request = Yii::$app->request;
        $page = (int)$request->get('page', 1);
        $limit = (int)$request->get('limit', 20);

        // Проверяем существование постройки
        $building = Building::find()
            ->where(['id' => $id, 'status' => Building::STATUS_ACTIVE])
            ->one();

        if (!$building) {
            return $this->errorResponse('BUILDING_NOT_FOUND', 'Постройка не найдена', [], 404);
        }

        $query = BuildingLike::find()
            ->where(['building_id' => $id])
            ->with(['user']);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'page' => $page - 1, // ActiveDataProvider использует 0-based индексацию
                'pageSize' => $limit,
            ],
            'sort' => [
                'defaultOrder' => ['created_at' => SORT_DESC],
            ],
        ]);

        $users = [];
        foreach ($dataProvider->getModels() as $like) {
            if ($like->user) {
                $users[] = [
                    'id' => $like->user->id,
                    'username' => $like->user->username,
                    'steamId' => $like->user->steam_id,
                    'avatar' => $like->user->getAvatar(),
                    'likedAt' => $like->created_at,
                ];
            }
        }

        $pagination = $dataProvider->getPagination();
        $totalPages = $pagination->getPageCount();

        return $this->successResponse([
            'users' => $users,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalCount' => $dataProvider->getTotalCount(),
                'pageSize' => $limit,
                'hasMore' => $page < $totalPages,
            ],
        ]);
    }

    /**
     * Постановка/снятие лайка постройке
     *
     * @OA\Post(
     *     path="/v1/buildings/{id}/like",
     *     operationId="likeBuilding",
     *     tags={"Buildings"},
     *     summary="Поставить или убрать лайк постройке",
     *     description="Переключает лайк постройке. Требует авторизации.",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID постройки",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Лайк переключен",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Требуется авторизация"),
     *     @OA\Response(response=404, description="Постройка не найдена")
     * )
     */
    public function actionLike($id)
    {
        $user = $this->getCurrentUser();

        $building = Building::find()
            ->where(['id' => $id, 'status' => Building::STATUS_ACTIVE])
            ->one();

        if (!$building) {
            return $this->errorResponse('BUILDING_NOT_FOUND', 'Постройка не найдена', [], 404);
        }

        // Проверяем, есть ли уже лайк от этого пользователя
        $userLike = BuildingLike::find()
            ->where(['building_id' => $id, 'user_id' => $user->id])
            ->one();

        if ($userLike) {
            // Убираем лайк
            $userLike->delete();
            $building->likes = max(0, $building->likes - 1);
            $building->save(false);
            $isLiked = false;
        } else {
            // Ставим лайк
            $like = new BuildingLike();
            $like->user_id = $user->id;
            $like->building_id = $id;
            $like->type = BuildingLike::TYPE_LIKE;
            $like->created_at = date('Y-m-d H:i:s');

            if ($like->save()) {
                $building->likes += 1;
                $building->save(false);
                $isLiked = true;
            } else {
                return $this->errorResponse('SAVE_ERROR', 'Ошибка при сохранении лайка', $like->errors, 500);
            }
        }

        return $this->successResponse([
            'isLiked' => $isLiked,
            'likes' => $building->likes,
        ]);
    }

    /**
     * Создание новой постройки
     *
     * @OA\Post(
     *     path="/v1/buildings/create",
     *     operationId="createBuilding",
     *     tags={"Buildings"},
     *     summary="Создать новую постройку",
     *     description="Создает новую постройку и отправляет на модерацию. Требует авторизации.",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name", "description", "location", "server_tag"},
     *                 @OA\Property(property="name", type="string", description="Название постройки", maxLength=255),
     *                 @OA\Property(property="description", type="string", description="Описание постройки", maxLength=512),
     *                 @OA\Property(property="location", type="string", description="Квадрат расположения (например: E14)", maxLength=3),
     *                 @OA\Property(property="server_tag", type="string", description="Тег сервера", maxLength=11),
     *                 @OA\Property(property="residents", type="array", description="Массив ID жильцов (опционально)", @OA\Items(type="integer")),
     *                 @OA\Property(property="images", type="array", description="Массив файлов изображений (до 4 файлов). Альтернатива: imageFileNames", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="imageFileNames", type="array", description="Массив имен уже загруженных файлов (через upload-image). Альтернатива: images", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Постройка создана",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Ошибка валидации"),
     *     @OA\Response(response=401, description="Требуется авторизация")
     * )
     */
    public function actionCreate()
    {
        $user = $this->getCurrentUser();

        // Проверяем, что у пользователя нет построек на модерации
        $userBuildingsWait = Building::find()
            ->where(['user_id' => $user->id, 'status' => Building::STATUS_WAIT])
            ->exists();

        if ($userBuildingsWait) {
            return $this->errorResponse('BUILDING_WAIT_EXISTS', 'Вы не можете добавить новую постройку, пока у вас есть постройки на модерации', [], 400);
        }

        // Проверяем, что у пользователя есть сервер
        if (empty($user->server)) {
            return $this->errorResponse('NO_SERVER', 'Добавить постройку могут только игроки', [], 400);
        }

        $request = Yii::$app->request;
        $name = trim($request->post('name', ''));
        $description = trim($request->post('description', ''));
        $location = trim($request->post('location', ''));
        $serverTag = trim($request->post('server_tag', ''));
        $residents = $request->post('residents', []);

        // Валидация обязательных полей
        if (empty($name)) {
            return $this->errorResponse('VALIDATION_ERROR', 'Название постройки обязательно', ['name' => 'Название не может быть пустым'], 400);
        }
        if (empty($description)) {
            return $this->errorResponse('VALIDATION_ERROR', 'Описание постройки обязательно', ['description' => 'Описание не может быть пустым'], 400);
        }
        if (empty($location)) {
            return $this->errorResponse('VALIDATION_ERROR', 'Квадрат расположения обязателен', ['location' => 'Квадрат расположения не может быть пустым'], 400);
        }
        if (empty($serverTag)) {
            return $this->errorResponse('VALIDATION_ERROR', 'Тег сервера обязателен', ['server_tag' => 'Тег сервера не может быть пустым'], 400);
        }

        // Проверяем существование сервера
        $server = Servers::find()
            ->where(['tag' => $serverTag])
            ->one();

        if (!$server) {
            return $this->errorResponse('SERVER_NOT_FOUND', 'Сервер не найден', [], 404);
        }

        // Получаем изображения - либо файлы, либо уже загруженные имена файлов
        $images = UploadedFile::getInstancesByName('images');
        $imageFileNames = $request->post('imageFileNames', []);

        // Определяем количество новых изображений, которые будут загружены
        $newImagesCount = 0;
        if (!empty($imageFileNames) && is_array($imageFileNames)) {
            $imageFileNames = array_filter($imageFileNames);
            $newImagesCount = count($imageFileNames);
            if ($newImagesCount > 4) {
                $newImagesCount = 4;
                $imageFileNames = array_slice($imageFileNames, 0, 4);
            }
        } else {
            // Иначе используем загруженные файлы
            if (empty($images)) {
                return $this->errorResponse('VALIDATION_ERROR', 'Необходимо загрузить хотя бы одно изображение (через upload-image или напрямую)', ['images' => 'Изображения обязательны'], 400);
            }
            $newImagesCount = count($images);
            if ($newImagesCount > 4) {
                $newImagesCount = 4;
                $images = array_slice($images, 0, 4);
            }
        }

        // Проверяем лимит загрузки изображений (не более 10 за час)
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $oneHourAgoTimestamp = strtotime($oneHourAgo);

        // Считаем изображения, привязанные к постройкам пользователя
        $imagesInBuildings = BuildingImage::find()
            ->joinWith('building')
            ->where(['building.user_id' => $user->id])
            ->andWhere(['>=', 'building_image.created_at', $oneHourAgo])
            ->count();

        // Считаем загрузки через upload-image за последний час (из кеша)
        $cacheKey = 'building_image_uploads_' . $user->id;
        $uploadedImages = Yii::$app->cache->get($cacheKey);
        if ($uploadedImages === false) {
            $uploadedImages = [];
        }

        // Фильтруем загрузки за последний час
        $recentUploads = array_filter($uploadedImages, function($timestamp) use ($oneHourAgoTimestamp) {
            return $timestamp >= $oneHourAgoTimestamp;
        });

        $imagesCount = $imagesInBuildings + count($recentUploads);

        // Проверяем, не превысит ли загрузка лимит
        if ($imagesCount + $newImagesCount > 10) {
            $remaining = 10 - $imagesCount;
            return $this->errorResponse('UPLOAD_LIMIT_EXCEEDED', "Превышен лимит загрузки изображений. Максимум 10 изображений в час. Вы можете загрузить еще {$remaining} изображений", [
                'limit' => 10,
                'current' => $imagesCount,
                'tryingToUpload' => $newImagesCount,
                'remaining' => max(0, $remaining),
                'resetAt' => date('Y-m-d H:i:s', strtotime('+1 hour', strtotime($oneHourAgo))),
            ], 429);
        }

        // Если загружаются файлы напрямую, валидируем их
        if (!empty($images)) {
            // Валидация изображений
            $validator = new ImageValidator([
                'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif'],
                'maxWidth' => 6000,
                'maxHeight' => 6000,
                'skipOnEmpty' => true,
            ]);

            foreach ($images as $img) {
                if (!$validator->validate($img, $error)) {
                    return $this->errorResponse('VALIDATION_ERROR', 'Разрешено загружать только файлы PNG, JPEG, GIF', ['images' => $error], 400);
                }
            }
        }

        // Подготавливаем массив жильцов
        $residentsArray = [];
        if (is_array($residents)) {
            $residentsArray = array_map('intval', $residents);
            $residentsArray = array_filter($residentsArray);
        }

        // Добавляем владельца в список жильцов, если его там нет
        if (!in_array($user->id, $residentsArray)) {
            $residentsArray[] = $user->id;
        }

        // Проверяем лимит команды
        if (!empty($server->team_limit) && $server->team_limit < count($residentsArray)) {
            return $this->errorResponse('TEAM_LIMIT_EXCEEDED', 'Нарушение лимита команды, этот сервер для ' . $server->team_limit . ' человек', ['residents' => 'Превышен лимит команды'], 400);
        }

        // Очищаем данные от HTML тегов и опасных символов (без HTMLPurifier, чтобы избежать проблем с правами доступа)
        $cleanName = strip_tags($name);
        $cleanDescription = strip_tags($description);
        // Location должен быть в формате "E14" (буква + цифры), очищаем от всего лишнего
        $cleanLocation = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim($location)));
        
        // Дополнительная проверка длины
        if (mb_strlen($cleanName) > 255) {
            $cleanName = mb_substr($cleanName, 0, 255);
        }
        if (mb_strlen($cleanDescription) > 512) {
            $cleanDescription = mb_substr($cleanDescription, 0, 512);
        }
        if (mb_strlen($cleanLocation) > 3) {
            $cleanLocation = mb_substr($cleanLocation, 0, 3);
        }

        // Создаем постройку
        $building = new Building();
        $building->user_id = $user->id;
        $building->name = $cleanName;
        $building->description = $cleanDescription;
        $building->location = $cleanLocation;
        $building->server_tag = $serverTag;
        $building->status = Building::STATUS_WAIT;
        $building->likes = 0;

        // Формируем строку вайпа
        $wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');
        $building->wipe = $wipeDate;
        $building->created_at = date('Y-m-d H:i:s');

        // Используем save(false) чтобы пропустить валидацию с HTMLPurifier
        // Данные уже очищены выше
        if (!$building->save(false)) {
            return $this->errorResponse('SAVE_ERROR', 'Ошибка при сохранении постройки', $building->errors, 500);
        }

        // Добавляем жильцов
        foreach ($residentsArray as $residentId) {
            $resident = new BuildingResident();
            $resident->building_id = $building->id;
            $resident->user_id = $residentId;
            $resident->save(false);
        }

        // Загружаем изображения в S3
        $s3Api = Yii::$app->s3Api;
        $uploadedImages = [];

        // Если переданы имена уже загруженных файлов
        if (!empty($imageFileNames)) {
            foreach ($imageFileNames as $fileName) {
                // Проверяем, что файл существует в S3
                $s3KeyOriginal = 'uploads/buildings/' . $fileName;
                $publicUrl = $s3Api->getPublicUrl($s3KeyOriginal);

                // Сохраняем информацию об изображении в БД
                $buildingImage = new BuildingImage();
                $buildingImage->building_id = $building->id;
                $buildingImage->image = $fileName;
                $buildingImage->created_at = date('Y-m-d H:i:s');
                $buildingImage->save(false);

                $uploadedImages[] = $publicUrl;
            }
        } else {
            // Загружаем новые файлы
            foreach ($images as $i => $image) {
            if (empty($image->tempName)) {
                continue;
            }

            try {
                // Создаем временные файлы для обработки
                $tempDir = sys_get_temp_dir();
                $tempOriginal = $tempDir . '/' . uniqid('building_orig_') . '.png';
                $tempPreview = $tempDir . '/' . uniqid('building_preview_') . '.png';

                $fileName = $building->id . "_" . md5(time() . $i) . ".png";

                // Сохраняем оригинал во временный файл
                file_put_contents($tempOriginal, file_get_contents($image->tempName));

                // Оптимизируем оригинал через TinyPNG (не критично, если не получится)
                $this->optimizeImageWithTinify($tempOriginal);

                // Создаем превью используя метод из DropImage (с оптимизацией)
                if (!DropImage::resizeImage($tempOriginal, $tempPreview, 200)) {
                    // Если не получилось через DropImage, используем старый метод
                    $imagine = Image::getImagine();
                    $img = $imagine->open($tempOriginal);
                    $diffWidth = 1;
                    $diffHeight = 1;
                    $offsetX = 0;
                    $offsetY = 0;
                    $newWidth = 200;
                    $newHeight = 200;

                    if ($img->getSize()->getWidth() > $img->getSize()->getHeight()) {
                        $diffWidth = $img->getSize()->getWidth() / $img->getSize()->getHeight();
                        $newWidth = 200 * $diffWidth;
                        $offsetX = $newWidth / 2 - 100;
                    } else {
                        $diffHeight = $img->getSize()->getHeight() / $img->getSize()->getWidth();
                        $newHeight = 200 * $diffHeight;
                        $offsetY = $newHeight / 2 - 100;
                    }

                    if ($offsetY < 0) {
                        $offsetY = 0;
                    }
                    if ($offsetX < 0) {
                        $offsetX = 0;
                    }

                    $img
                        ->resize(new Box($newWidth, $newHeight))
                        ->crop(new Point($offsetX, $offsetY), new Box(200, 200))
                        ->save($tempPreview, ['quality' => 70]);
                }

                // Загружаем оригинал в S3
                $s3KeyOriginal = 'uploads/buildings/' . $fileName;
                $originalContent = file_get_contents($tempOriginal);
                $s3ResultOriginal = $s3Api->putFile($s3KeyOriginal, $originalContent, 'image/png');

                // Загружаем превью в S3
                $s3KeyPreview = 'uploads/buildings/preview_' . $fileName;
                $previewContent = file_get_contents($tempPreview);
                $s3ResultPreview = $s3Api->putFile($s3KeyPreview, $previewContent, 'image/png');

                // Удаляем временные файлы
                @unlink($tempOriginal);
                @unlink($tempPreview);

                if ($s3ResultOriginal === false || $s3ResultPreview === false) {
                    Yii::error('Error uploading building image to S3', __METHOD__);
                    continue;
                }

                // Сохраняем информацию об изображении в БД
                $buildingImage = new BuildingImage();
                $buildingImage->building_id = $building->id;
                $buildingImage->image = $fileName;
                $buildingImage->created_at = date('Y-m-d H:i:s');
                $buildingImage->save(false);

                $uploadedImages[] = $buildingImage->getPublicUrl();
            } catch (\Exception $e) {
                Yii::error('Error processing building image: ' . $e->getMessage(), __METHOD__);
                continue;
            }
            }
        }

        // Перезагружаем постройку с отношениями
        $building = Building::find()
            ->where(['id' => $building->id])
            ->with(['buildingImage', 'user', 'server', 'buildingResident.user'])
            ->one();

        // Формируем ответ
        $images = [];
        if ($building->buildingImage) {
            foreach ($building->buildingImage as $img) {
                $images[] = $img->getPublicUrl();
            }
        }

        $residents = [];
        if ($building->buildingResident) {
            foreach ($building->buildingResident as $resident) {
                if ($resident->user) {
                    $residents[] = [
                        'id' => $resident->user->id,
                        'username' => $resident->user->username,
                        'steamId' => $resident->user->steam_id,
                        'avatar' => $resident->user->getAvatar(),
                    ];
                }
            }
        }

        return $this->successResponse([
            'id' => $building->id,
            'name' => $building->name,
            'description' => $building->description,
            'location' => $building->location,
            'images' => $images,
            'image' => !empty($images) ? $images[0] : null,
            'likes' => $building->likes ?? 0,
            'wipe' => $building->wipe,
            'status' => $building->status,
            'server' => $building->server ? [
                'tag' => $building->server->tag,
                'name' => $building->server->monitoring_name ?? $building->server->tag,
            ] : null,
            'user' => $building->user ? [
                'id' => $building->user->id,
                'username' => $building->user->username,
                'steamId' => $building->user->steam_id,
                'avatar' => $building->user->getAvatar(),
            ] : null,
            'residents' => $residents,
            'createdAt' => $building->created_at,
        ], [], 201);
    }

    /**
     * Загрузка изображения для постройки
     *
     * @OA\Post(
     *     path="/v1/buildings/upload-image",
     *     operationId="uploadBuildingImage",
     *     tags={"Buildings"},
     *     summary="Загрузить изображение для постройки",
     *     description="Загружает изображение в S3 и возвращает URL. Требует авторизации.",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"image"},
     *                 @OA\Property(property="image", type="string", format="binary", description="Файл изображения")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Изображение загружено",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Ошибка валидации"),
     *     @OA\Response(response=401, description="Требуется авторизация")
     * )
     */
    public function actionUploadImage()
    {
        $user = $this->getCurrentUser();

        // Проверяем лимит загрузки изображений (не более 10 за час)
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));

        // Считаем изображения, привязанные к постройкам пользователя
        $imagesInBuildings = BuildingImage::find()
            ->joinWith('building')
            ->where(['building.user_id' => $user->id])
            ->andWhere(['>=', 'building_image.created_at', $oneHourAgo])
            ->count();

        // Считаем загрузки через upload-image за последний час (из кеша)
        $cacheKey = 'building_image_uploads_' . $user->id;
        $uploadedImages = Yii::$app->cache->get($cacheKey);
        if ($uploadedImages === false) {
            $uploadedImages = [];
        }

        // Фильтруем загрузки за последний час
        $oneHourAgoTimestamp = strtotime($oneHourAgo);
        $recentUploads = array_filter($uploadedImages, function($timestamp) use ($oneHourAgoTimestamp) {
            return $timestamp >= $oneHourAgoTimestamp;
        });

        $imagesCount = $imagesInBuildings + count($recentUploads);

        if ($imagesCount >= 10) {
            return $this->errorResponse('UPLOAD_LIMIT_EXCEEDED', 'Превышен лимит загрузки изображений. Максимум 10 изображений в час', [
                'limit' => 10,
                'current' => $imagesCount,
                'resetAt' => date('Y-m-d H:i:s', strtotime('+1 hour', strtotime($oneHourAgo))),
            ], 429);
        }

        // Получаем файл
        $file = UploadedFile::getInstanceByName('image');

        if (!$file) {
            return $this->errorResponse('FILE_REQUIRED', 'Файл изображения обязателен', [], 400);
        }

        // Валидация изображения
        $validator = new ImageValidator([
            'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif'],
            'maxWidth' => 6000,
            'maxHeight' => 6000,
            'skipOnEmpty' => true,
        ]);

        if (!$validator->validate($file, $error)) {
            return $this->errorResponse('VALIDATION_ERROR', 'Разрешено загружать только файлы PNG, JPEG, GIF', ['image' => $error], 400);
        }

        // Проверка размера файла (максимум 10 МБ)
        if ($file->size > 10 * 1024 * 1024) {
            return $this->errorResponse('FILE_TOO_LARGE', 'Файл слишком большой (максимум 10 МБ)', [], 400);
        }

        try {
            $s3Api = Yii::$app->s3Api;

            // Создаем временные файлы для обработки
            $tempDir = sys_get_temp_dir();
            $tempOriginal = $tempDir . '/' . uniqid('building_orig_') . '.png';
            $tempPreview = $tempDir . '/' . uniqid('building_preview_') . '.png';

            // Генерируем уникальное имя файла
            $fileName = uniqid('building_') . '_' . md5(time() . $user->id) . ".png";

            // Сохраняем оригинал во временный файл
            file_put_contents($tempOriginal, file_get_contents($file->tempName));

            // Оптимизируем оригинал через TinyPNG (не критично, если не получится)
            $this->optimizeImageWithTinify($tempOriginal);

            // Создаем превью используя метод из DropImage (с оптимизацией)
            if (!DropImage::resizeImage($tempOriginal, $tempPreview, 200)) {
                // Если не получилось через DropImage, используем старый метод
                $imagine = Image::getImagine();
                $img = $imagine->open($tempOriginal);
                $diffWidth = 1;
                $diffHeight = 1;
                $offsetX = 0;
                $offsetY = 0;
                $newWidth = 200;
                $newHeight = 200;

                if ($img->getSize()->getWidth() > $img->getSize()->getHeight()) {
                    $diffWidth = $img->getSize()->getWidth() / $img->getSize()->getHeight();
                    $newWidth = 200 * $diffWidth;
                    $offsetX = $newWidth / 2 - 100;
                } else {
                    $diffHeight = $img->getSize()->getHeight() / $img->getSize()->getWidth();
                    $newHeight = 200 * $diffHeight;
                    $offsetY = $newHeight / 2 - 100;
                }

                if ($offsetY < 0) {
                    $offsetY = 0;
                }
                if ($offsetX < 0) {
                    $offsetX = 0;
                }

                $img
                    ->resize(new Box($newWidth, $newHeight))
                    ->crop(new Point($offsetX, $offsetY), new Box(200, 200))
                    ->save($tempPreview, ['quality' => 70]);
            }

            // Загружаем оригинал в S3
            $s3KeyOriginal = 'uploads/buildings/' . $fileName;
            $originalContent = file_get_contents($tempOriginal);
            $s3ResultOriginal = $s3Api->putFile($s3KeyOriginal, $originalContent, 'image/png');

            // Загружаем превью в S3
            $s3KeyPreview = 'uploads/buildings/preview_' . $fileName;
            $previewContent = file_get_contents($tempPreview);
            $s3ResultPreview = $s3Api->putFile($s3KeyPreview, $previewContent, 'image/png');

            // Удаляем временные файлы
            @unlink($tempOriginal);
            @unlink($tempPreview);

            if ($s3ResultOriginal === false || $s3ResultPreview === false) {
                Yii::error('Error uploading building image to S3', __METHOD__);
                return $this->errorResponse('UPLOAD_ERROR', 'Ошибка при загрузке изображения в S3', [], 500);
            }

            // Сохраняем информацию о загрузке в кеш для отслеживания лимита
            $cacheKey = 'building_image_uploads_' . $user->id;
            $uploadedImages = Yii::$app->cache->get($cacheKey);
            if ($uploadedImages === false) {
                $uploadedImages = [];
            }
            $uploadedImages[] = time();
            // Храним в кеше на 2 часа (чтобы покрыть окно в 1 час)
            Yii::$app->cache->set($cacheKey, $uploadedImages, 7200);

            // Возвращаем URL изображения
            $imageUrl = $s3Api->getPublicUrl($s3KeyOriginal);
            $previewUrl = $s3Api->getPublicUrl($s3KeyPreview);

            return $this->successResponse([
                'url' => $imageUrl,
                'previewUrl' => $previewUrl,
                'fileName' => $fileName,
            ]);

        } catch (\Exception $e) {
            Yii::error('Error processing building image: ' . $e->getMessage(), __METHOD__);
            return $this->errorResponse('PROCESSING_ERROR', 'Ошибка при обработке изображения: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Оптимизация изображения через TinyPNG
     *
     * @param string $filePath Путь к файлу изображения
     * @return bool Успешность оптимизации
     */
    protected function optimizeImageWithTinify($filePath)
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        try {
            // Пробуем разные ключи TinyPNG
            $keys = [
                "dY4rkCVRZxqxWD3wZcCdysWBbM7CGWB8",
                "SQMyJN0ZNs1zQfzrwBjMcsRHCnpffCbl",
            ];

            foreach ($keys as $key) {
                try {
                    \Tinify\setKey($key);
                    $source = \Tinify\fromFile($filePath);
                    $source->toFile($filePath); // перезаписывает исходный файл
                    return true;
                } catch(\Tinify\Exception $e) {
                    // Пробуем следующий ключ
                    continue;
                }
            }

            // Если все ключи не сработали, просто логируем
            Yii::info('Tinify compression skipped for building image', __METHOD__);
            return false;
        } catch(\Exception $e) {
            // Любая другая ошибка - просто пропускаем сжатие
            Yii::info('Tinify compression error: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}

