<?php

namespace api\controllers\v1;

use Yii;
use common\models\box\Drop;
use common\models\box\Category;
use common\models\box\DropImage;
use common\models\box\DropDrop;
use common\models\box\DropFavorite;
use common\models\user\UserDrop;
use common\models\invoice\Invoice;
use api\components\jwt\JwtAuthFilter;
use api\components\jwt\JwtService;
use common\models\user\User;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с товарами и категориями
 *
 * @package api\controllers\v1
 * @OA\Tag(name="Products")
 */
class ProductsController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // JWT авторизация требуется для покупки товара
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'except' => ['index', 'categories', 'view', 'options'], // Публичные методы не требуют авторизации
        ];

        return $behaviors;
    }
    /**
     * Получение списка категорий
     *
     * @OA\Get(
     *     path="/v1/products/categories",
     *     operationId="getProductCategories",
     *     tags={"Products"},
     *     summary="Получить список категорий товаров",
     *     @OA\Parameter(
     *         name="show_main_block",
     *         in="query",
     *         description="Показывать только категории для главной страницы",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список категорий",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionCategories()
    {
        $showMainBlock = Yii::$app->request->get('show_main_block');
        
        // Кэшируем категории на 1 час, с учетом параметра show_main_block
        $cacheKey = 'api_products_categories_' . ($showMainBlock !== null ? (int)$showMainBlock : 'all');
        $cache = Yii::$app->cache;
        $formattedCategories = $cache->get($cacheKey);

        if ($formattedCategories === false) {
            $query = Category::find();
            
            if ($showMainBlock !== null) {
                $query->andWhere(['show_main_block' => (int)$showMainBlock]);
            }
            
            $query->orderBy(['sort' => SORT_ASC, 'name' => SORT_ASC]);
            
            $categories = $query->all();
            
            $formattedCategories = [];
            foreach ($categories as $category) {
                // Форматируем изображение категории для S3, если оно есть
                $categoryImage = null;
                if (!empty($category->image)) {
                    // Если изображение начинается с /images/, добавляем префикс uploads для S3
                    if (strpos($category->image, '/images/') === 0) {
                        // Формируем ключ для S3: /images/... -> uploads/images/...
                        $s3Key = 'uploads' . $category->image;
                        $categoryImage = Yii::$app->s3Api->getPublicUrl($s3Key);
                    } elseif (strpos($category->image, '/uploads/') === 0) {
                        // Если изображение уже начинается с /uploads/, используем как есть
                        $s3Key = ltrim($category->image, '/');
                        $categoryImage = Yii::$app->s3Api->getPublicUrl($s3Key);
                    } else {
                        // Если это уже полный URL или относительный путь, используем как есть
                        $categoryImage = $category->image;
                    }
                }
                
                $formattedCategories[] = [
                    'id' => $category->id,
                    'name' => Yii::t('database', $category->name),
                    'image' => $categoryImage,
                    'tag' => $category->tag ?? null,
                ];
            }
            
            // Сохраняем в кэш на 1 час (3600 секунд)
            $cache->set($cacheKey, $formattedCategories, 3600);
        }
        
        return $this->successResponse($formattedCategories);
    }

    /**
     * Получение списка товаров
     *
     * @OA\Get(
     *     path="/v1/products",
     *     operationId="getProducts",
     *     tags={"Products"},
     *     summary="Получить список товаров",
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Количество товаров на страницу",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Parameter(
     *         name="offset",
     *         in="query",
     *         description="Смещение для пагинации",
     *         required=false,
     *         @OA\Schema(type="integer", default=0)
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="ID категории",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="show_main_block",
     *         in="query",
     *         description="0 = только товары не из главного блока (сетка), 1 = только наборы (главный блок). Не передавать = все товары",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Поиск по названию",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Сортировка (price_asc, price_desc, name_asc, name_desc, created_at_desc)",
     *         required=false,
     *         @OA\Schema(type="string", default="sort")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список товаров",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionIndex()
    {
        $limit = (int)Yii::$app->request->get('limit', 20);
        $offset = (int)Yii::$app->request->get('offset', 0);
        $categoryId = Yii::$app->request->get('category_id');
        $showMainBlock = Yii::$app->request->get('show_main_block');
        $search = Yii::$app->request->get('search');
        $sort = Yii::$app->request->get('sort', 'sort');

        // Опциональная JWT: для авторизованного пользователя отдаём товары с избранными в начале
        $this->tryAuthenticateUserFromJwt();
        $userId = Yii::$app->user->getIsGuest() ? null : (int)Yii::$app->user->getId();
        $favoriteDropIds = $userId ? DropFavorite::getFavoriteDropIds($userId) : [];

        // Кэшируем только базовый список (без фильтров, первая страница, дефолтная сортировка)
        // Не кэшируем при персональной сортировке по избранному
        $hasFilters = !empty($categoryId) || !empty($search);
        $isDefaultSort = $sort === 'sort';
        $cacheKey = null;
        $cachedData = null;
        $cacheSuffix = ($showMainBlock !== null && $showMainBlock !== '') ? '_main_' . (int)$showMainBlock : '_all';

        if (!$hasFilters && $offset === 0 && $isDefaultSort && empty($favoriteDropIds)) {
            $cacheKey = 'api_products_list_' . $limit . $cacheSuffix;
            $cache = Yii::$app->cache;
            $cachedData = $cache->get($cacheKey);

            if ($cachedData !== false && is_array($cachedData)) {
                $cachedProducts = $cachedData['data'] ?? $cachedData;
                $cachedPagination = $cachedData['pagination'] ?? [];
                return $this->successResponse($cachedProducts, ['pagination' => $cachedPagination]);
            }
        }

        if ($cachedData === false || $cachedData === null || $hasFilters || $offset > 0 || !$isDefaultSort || !empty($favoriteDropIds)) {
            $query = Drop::find()
                ->where(['status' => Drop::STATUS_ACTIVE])
                ->andWhere(['market_status' => Drop::MARKET_STATUS_ACTIVE])
                ->with(['dropImages', 'subDrops.drop', 'subDrops.drop.dropImages']);

            if ($categoryId) {
                $query->andWhere(['category_id' => (int)$categoryId]);
            }

            if ($showMainBlock !== null && $showMainBlock !== '') {
                $query->andWhere(['show_main_block' => (int)$showMainBlock]);
            }

            if ($search) {
                $query->andFilterWhere(['like', 'name', $search])
                      ->orFilterWhere(['like', 'eng_name', $search]);
            }

            // Сортировка: при наличии избранного — сначала избранные, затем базовая сортировка
            if (!empty($favoriteDropIds)) {
                $safeIds = array_map('intval', $favoriteDropIds);
                $favoritesOrder = '(CASE WHEN id IN (' . implode(',', $safeIds) . ') THEN 0 ELSE 1 END) ASC';
                switch ($sort) {
                    case 'price_asc':
                        $query->orderBy(new Expression($favoritesOrder . ', price ASC'));
                        break;
                    case 'price_desc':
                        $query->orderBy(new Expression($favoritesOrder . ', price DESC'));
                        break;
                    case 'name_asc':
                        $query->orderBy(new Expression($favoritesOrder . ', name ASC'));
                        break;
                    case 'name_desc':
                        $query->orderBy(new Expression($favoritesOrder . ', name DESC'));
                        break;
                    case 'created_at_desc':
                        $query->orderBy(new Expression($favoritesOrder . ', created_at DESC'));
                        break;
                    default:
                        $query->orderBy(new Expression($favoritesOrder . ', sort ASC, created_at DESC'));
                        break;
                }
            } else {
                switch ($sort) {
                    case 'price_asc':
                        $query->orderBy(['price' => SORT_ASC]);
                        break;
                    case 'price_desc':
                        $query->orderBy(['price' => SORT_DESC]);
                        break;
                    case 'name_asc':
                        $query->orderBy(['name' => SORT_ASC]);
                        break;
                    case 'name_desc':
                        $query->orderBy(['name' => SORT_DESC]);
                        break;
                    case 'created_at_desc':
                        $query->orderBy(['created_at' => SORT_DESC]);
                        break;
                    default:
                        $query->orderBy(['sort' => SORT_ASC, 'created_at' => SORT_DESC]);
                        break;
                }
            }

            $dataProvider = new ActiveDataProvider([
                'query' => $query,
                'pagination' => [
                    'pageSize' => $limit,
                    'page' => floor($offset / $limit),
                ],
            ]);

            $products = [];
            foreach ($dataProvider->getModels() as $drop) {
                // Получаем первое изображение с S3 URL
                $imageUrl = null;
                $dropImages = $drop->dropImages;
                if (!empty($dropImages)) {
                    $firstImage = reset($dropImages);
                    if ($firstImage) {
                        $imageUrl = $firstImage->getImagePubUrl();
                    }
                }

                // Получаем субдропы (subDrops)
                $subDrops = [];
                $subDropsList = $drop->subDrops;
                if (!empty($subDropsList)) {
                    foreach ($subDropsList as $subDropRelation) {
                        // subDropRelation это DropDrop, нужно получить связанный Drop
                        $subDrop = $subDropRelation->drop;
                        if ($subDrop) {
                            // Получаем изображение субдропа с S3 URL
                            $subDropImage = null;
                            $subDropImages = $subDrop->dropImages;
                            if (!empty($subDropImages)) {
                                $firstSubImage = reset($subDropImages);
                                if ($firstSubImage) {
                                    $subDropImage = $firstSubImage->getImagePubUrl();
                                }
                            }
                            
                            $subDrops[] = [
                                'id' => $subDropRelation->id,
                                'drop_id' => $subDrop->id,
                                'count' => $subDropRelation->count ?? 1,
                                'name' => Yii::t('database', $subDrop->name ?? ''),
                                'price' => (float)($subDrop->price ?? 0),
                                'image' => $subDropImage,
                            ];
                        }
                    }
                }

                // Вычисляем реальную цену с учетом скидки
                // getRealPrice(false) вычисляет цену без floating, но может использовать Yii::$app->user
                // Для публичного доступа используем простое вычисление
                $basePrice = (float)$drop->price;
                $priceReal = $drop->discount && $drop->discount > 0
                    ? ceil($basePrice - ($basePrice * $drop->discount / 100))
                    : $basePrice;
                $price = $drop->discount && $drop->discount > 0
                    ? round($priceReal * (1 + $drop->discount / 100))
                    : $priceReal;

                $products[] = [
                    'id' => $drop->id,
                    'name' => Yii::t('database', $drop->name),
                    'image' => $imageUrl,
                    'price' => $price,
                    'priceReal' => $priceReal,
                    'discount' => $drop->discount ? (int)$drop->discount : null,
                    'count' => $drop->count ? (int)$drop->count : null,
                    'category_id' => $drop->category_id ? (int)$drop->category_id : null,
                    'description' => $drop->description ? Yii::t('database', $drop->description) : null,
                    'drop_type' => $drop->drop_type ? (int)$drop->drop_type : null,
                    'subDrops' => !empty($subDrops) ? $subDrops : null,
                    'floating_price_percent' => $drop->floating_price_percent ? (int)$drop->floating_price_percent : null,
                ];
            }

            $pagination = $dataProvider->getPagination();

            // Сохраняем в кэш только базовый список без персональной сортировки по избранному
            if (!$hasFilters && $offset === 0 && $isDefaultSort && empty($favoriteDropIds) && $cacheKey) {
                $responseData = [
                    'data' => $products,
                    'pagination' => [
                        'total' => $pagination->totalCount,
                        'limit' => $limit,
                        'offset' => $offset,
                        'hasMore' => ($offset + count($products)) < $pagination->totalCount,
                    ],
                ];
                $cache->set($cacheKey, $responseData, 300); // 5 минут
            }

            return $this->successResponse($products, [
                'pagination' => [
                    'total' => $pagination->totalCount,
                    'limit' => $limit,
                    'offset' => $offset,
                    'hasMore' => ($offset + count($products)) < $pagination->totalCount,
                ],
            ]);
        }
    }

    /**
     * Получение детальной информации о товаре
     *
     * @OA\Get(
     *     path="/v1/products/{id}",
     *     operationId="getProduct",
     *     tags={"Products"},
     *     summary="Получить информацию о товаре",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID товара",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Информация о товаре",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=404, description="Товар не найден")
     * )
     */
    public function actionView($id)
    {
        // Кэшируем детальную информацию о товаре на 10 минут
        $cacheKey = 'api_products_view_' . $id;
        $cache = Yii::$app->cache;
        $cachedProduct = $cache->get($cacheKey);

        if ($cachedProduct === false) {
            $drop = Drop::find()
                ->where(['id' => $id, 'status' => Drop::STATUS_ACTIVE])
                ->with(['dropImages', 'subDrops.drop', 'subDrops.drop.dropImages', 'category'])
                ->one();

            if (!$drop) {
                return $this->errorResponse('Товар не найден', 404);
            }

            // Получаем все изображения с S3 URL
            $images = [];
            $dropImages = $drop->dropImages;
            if (!empty($dropImages)) {
                foreach ($dropImages as $dropImage) {
                    $images[] = $dropImage->getImagePubUrl();
                }
            }

            // Получаем субдропы
            $subDrops = [];
            $subDropsList = $drop->subDrops;
            if (!empty($subDropsList)) {
                foreach ($subDropsList as $subDropRelation) {
                    // subDropRelation это DropDrop, нужно получить связанный Drop
                    $subDrop = $subDropRelation->drop;
                    if ($subDrop) {
                        // Получаем изображение субдропа с S3 URL
                        $subDropImage = null;
                        $subDropImages = $subDrop->dropImages;
                        if (!empty($subDropImages)) {
                            $firstSubImage = reset($subDropImages);
                            if ($firstSubImage) {
                                $subDropImage = $firstSubImage->getImagePubUrl();
                            }
                        }
                        
                        $subDrops[] = [
                            'id' => $subDropRelation->id,
                            'drop_id' => $subDrop->id,
                            'count' => $subDropRelation->count ?? 1,
                            'name' => Yii::t('database', $subDrop->name ?? ''),
                            'price' => (float)($subDrop->price ?? 0),
                            'image' => $subDropImage,
                        ];
                    }
                }
            }

            // Вычисляем реальную цену с учетом скидки
            $basePrice = (float)$drop->price;
            $priceReal = $drop->discount && $drop->discount > 0
                ? ceil($basePrice - ($basePrice * $drop->discount / 100))
                : $basePrice;
            $price = $drop->discount && $drop->discount > 0
                ? round($priceReal * (1 + $drop->discount / 100))
                : $priceReal;

            $product = [
                'id' => $drop->id,
                'name' => Yii::t('database', $drop->name),
                'images' => $images,
                'image' => !empty($images) ? $images[0] : null,
                'price' => $price,
                'priceReal' => $priceReal,
                'discount' => $drop->discount ? (int)$drop->discount : null,
                'count' => $drop->count ? (int)$drop->count : null,
                'category_id' => $drop->category_id ? (int)$drop->category_id : null,
                'category' => $drop->category ? [
                    'id' => $drop->category->id,
                    'name' => Yii::t('database', $drop->category->name),
                ] : null,
                'description' => $drop->description ? Yii::t('database', $drop->description) : null,
                'drop_type' => $drop->drop_type ? (int)$drop->drop_type : null,
                'subDrops' => !empty($subDrops) ? $subDrops : null,
                'floating_price_percent' => $drop->floating_price_percent ? (int)$drop->floating_price_percent : null,
                'quality' => $drop->quality ?? null,
                'command' => $drop->command ?? null,
            ];

            // Сохраняем в кэш на 10 минут
            $cache->set($cacheKey, $product, 600);
        } else {
            $product = $cachedProduct;
        }

        return $this->successResponse($product);
    }

    /**
     * Добавить/удалить товар из избранного
     *
     * @OA\Post(
     *     path="/v1/products/{id}/favorite",
     *     operationId="toggleProductFavorite",
     *     tags={"Products"},
     *     summary="Переключить избранное для товара",
     *     description="Требует JWT авторизации. Добавляет товар в избранное или удаляет из него.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID товара (drop_id)",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Статус избранного обновлен",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=404, description="Товар не найден")
     * )
     */
    public function actionToggleFavorite($id)
    {
        $user = $this->getCurrentUser();
        $drop = Drop::find()
            ->where(['id' => (int)$id, 'status' => Drop::STATUS_ACTIVE])
            ->one();

        if (!$drop) {
            return $this->errorResponse('PRODUCT_NOT_FOUND', Yii::t('common', 'Товар не найден'), [], 404);
        }

        $userId = $user->id;
        $dropId = (int)$id;
        $isFavorite = DropFavorite::isFavorite($userId, $dropId);

        if ($isFavorite) {
            DropFavorite::removeFromFavorite($userId, $dropId);
            return $this->successResponse([
                'isFavorite' => false,
                'message' => Yii::t('common', 'Товар удален из избранного'),
            ]);
        }

        DropFavorite::addToFavorite($userId, $dropId);
        return $this->successResponse([
            'isFavorite' => true,
            'message' => Yii::t('common', 'Товар добавлен в избранное'),
        ]);
    }

    /**
     * Покупка товара
     * 
     * @OA\Post(
     *     path="/v1/products/{id}/buy",
     *     operationId="buyProduct",
     *     tags={"Products"},
     *     summary="Купить товар",
     *     description="Требует JWT авторизации",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID товара",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="quantity", type="integer", example=1, description="Количество товара"),
     *                 @OA\Property(property="drop_id", type="integer", example=123, description="ID варианта товара (для TYPE_SELECT)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Товар куплен",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Ошибка при покупке"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=404, description="Товар не найден")
     * )
     */
    public function actionBuy($id)
    {
        $user = $this->getCurrentUser();
        
        // Получаем товар
        $drop = Drop::find()
            ->where(['id' => $id, 'status' => Drop::STATUS_ACTIVE])
            ->one();
        
        if (!$drop) {
            return $this->errorResponse('PRODUCT_NOT_FOUND', 'Товар не найден или неактивен', [], 404);
        }
        
        $post = Yii::$app->request->post();
        $quantity = isset($post['quantity']) ? (int)$post['quantity'] : 1;
        $selectedDropId = $post['drop_id'] ?? null;
        
        // Для TYPE_SELECT (drop_type = 3) требуется выбрать вариант
        if ($drop->drop_type == 3 && empty($selectedDropId)) {
            return $this->errorResponse('DROP_ID_REQUIRED', 'Необходимо выбрать вариант товара (drop_id)', [], 400);
        }
        
        // Определяем какой drop покупать (для TYPE_SELECT - выбранный вариант, иначе - сам товар)
        $targetDrop = $drop;
        $targetDropId = $drop->id;
        
        if ($drop->drop_type == 3 && $selectedDropId) {
            // Для TYPE_SELECT проверяем, что выбранный вариант существует
            $subDropRelation = DropDrop::find()
                ->where(['parent_drop_id' => $drop->id, 'drop_id' => $selectedDropId])
                ->one();
            
            if (!$subDropRelation) {
                return $this->errorResponse('INVALID_DROP_ID', 'Выбранный вариант товара не найден', [], 400);
            }
            
            $targetDrop = Drop::findOne($selectedDropId);
            if (!$targetDrop || $targetDrop->status != Drop::STATUS_ACTIVE) {
                return $this->errorResponse('INVALID_DROP_ID', 'Выбранный вариант товара неактивен', [], 400);
            }
            $targetDropId = $selectedDropId;
        }
        
        // Рассчитываем цену (упрощенная версия, без floating price для API)
        $basePrice = $targetDrop->price - ($targetDrop->price * ($targetDrop->discount ?? 0) / 100);
        $pricePerItem = ceil($basePrice);
        $totalPrice = $pricePerItem * $quantity;
        
        // Проверяем баланс
        $balance = $user->getPersonalBalance();
        if ($totalPrice > $balance->balanceCeil) {
            return $this->errorResponse('INSUFFICIENT_FUNDS', 'Недостаточно средств на счете', [
                'required' => $totalPrice,
                'available' => $balance->balanceCeil,
            ], 400);
        }
        
        // Начинаем транзакцию
        $dbTransaction = Yii::$app->db->beginTransaction();
        try {
            // Создаем Invoice для списания средств
            $comment = Yii::t('common', 'Покупка предмета "{PARAMS_PREDNAME}"', [
                'PARAMS_PREDNAME' => Yii::t('database', $targetDrop->name)
            ]);
            
            Invoice::createRecord(
                $user->id,
                $totalPrice,
                Invoice::TYPE_PAYMENT_MARKET_DROP,
                null, // box_id
                null, // sets_id
                $targetDropId, // drop_id
                $comment
            );
            
            // Создаем UserDrop записи
            if ($drop->drop_type == 2) {
                // TYPE_SET - создаем записи для всех subDrops
                $subDrops = DropDrop::find()
                    ->where(['parent_drop_id' => $drop->id])
                    ->with('drop')
                    ->all();
                
                foreach ($subDrops as $subDropRelation) {
                    if ($subDropRelation->drop) {
                        $subDropCount = ($subDropRelation->count ?? 1) * $quantity;
                        UserDrop::createRecord(
                            $user->id,
                            $subDropRelation->drop_id,
                            null, // box_id
                            null, // sets_id
                            UserDrop::STATUS_ACTIVE,
                            false, // auto
                            $subDropCount, // count
                            null, // created_at
                            $drop->id // parent_drop_id
                        );
                    }
                }
            } else {
                // Обычный товар или TYPE_SELECT - создаем запись для выбранного товара
                $dropCount = ($targetDrop->count ?? 1) * $quantity;
                UserDrop::createRecord(
                    $user->id,
                    $targetDropId,
                    null, // box_id
                    null, // sets_id
                    UserDrop::STATUS_ACTIVE,
                    false, // auto
                    $dropCount, // count
                    null, // created_at
                    $drop->drop_type == 3 ? $drop->id : null // parent_drop_id для TYPE_SELECT
                );
            }
            
            $dbTransaction->commit();
            
            // Получаем обновленный баланс
            $balance->recalculateBalance();
            $newBalance = $balance->balanceCeil;
            
            // Отправляем уведомление через WebSocket
            try {
                \console\controllers\NotificationServer::broadcastPurchaseNotification($user->id, $newBalance);
            } catch (\Exception $ex) {
                Yii::warning('WebSocket broadcast failed: ' . $ex->getMessage());
            }
            
            return $this->successResponse([
                'message' => 'Товар успешно приобретен',
                'newBalance' => $newBalance,
            ]);
            
        } catch (\Exception $e) {
            $dbTransaction->rollBack();
            Yii::error('Error buying product: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'products');
            return $this->errorResponse('PURCHASE_ERROR', 'Произошла ошибка при покупке товара: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Опциональная JWT-авторизация: при наличии токена в запросе устанавливает пользователя в Yii::$app->user.
     * Используется в actionIndex для персональной сортировки (избранное в начале). Не выбрасывает исключений.
     */
    private function tryAuthenticateUserFromJwt(): void
    {
        if (Yii::$app->request->getMethod() === 'OPTIONS') {
            return;
        }
        /** @var JwtService $jwt */
        $jwt = Yii::$app->has('jwt') ? Yii::$app->get('jwt') : new JwtService();
        $token = $jwt->extractTokenFromRequest(Yii::$app->request);
        if (empty($token)) {
            return;
        }
        try {
            $payload = $jwt->validateToken($token);
            $userId = $jwt->getUserId($payload);
            $steamId = $jwt->getSteamId($payload);
            if (empty($userId) && empty($steamId)) {
                return;
            }
            $user = null;
            if ($userId) {
                $user = User::findIdentity($userId);
            }
            if (!$user && $steamId) {
                $user = User::find()->where(['steam_id' => $steamId])->one();
            }
            if ($user) {
                Yii::$app->user->login($user, 0);
            }
        } catch (\Throwable $e) {
            // Игнорируем: запрос остаётся без авторизации
        }
    }
}

