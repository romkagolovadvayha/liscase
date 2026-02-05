<?php

namespace api\controllers\v1;

use Yii;
use common\models\serverskin\ServerSkin;
use common\models\serverskin\ServerSkinCategory;
use common\models\serverskin\ServerSkinLike;
use common\models\box\DropImage;
use common\models\user\User;
use frontend\forms\serverskin\ServerSkinForm;
use yii\data\ActiveDataProvider;
use api\components\jwt\JwtAuthFilter;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с кастомными скинами из мастерской Steam
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="CustomSkins")
 */
class CustomSkinsController extends BaseApiController
{
    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // JWT авторизация требуется для создания скина и постановки лайка
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'only' => ['create', 'like'],
            'except' => ['index', 'likes', 'categories', 'options'],
        ];

        return $behaviors;
    }

    /**
     * Получение списка скинов с пагинацией
     * 
     * @OA\Get(
     *     path="/v1/custom-skins",
     *     operationId="getCustomSkins",
     *     tags={"CustomSkins"},
     *     summary="Получить список скинов",
     *     description="Возвращает список скинов с фильтрацией и пагинацией",
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
     *         description="Количество скинов на странице",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="ID категории для фильтрации",
     *         required=false,
     *         @OA\Schema(type="integer")
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
     *         description="Список скинов",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionIndex()
    {
        $request = Yii::$app->request;
        $page = (int)$request->get('page', 1);
        $limit = (int)$request->get('limit', 10);
        $categoryId = $request->get('category_id');
        $search = $request->get('search');
        $sort = $request->get('sort', 'created_at');
        $order = $request->get('order', 'desc');

        // Кэшируем только базовый список (без фильтров, первая страница, дефолтная сортировка)
        $hasFilters = !empty($categoryId) || !empty($search);
        $isDefaultSort = $sort === 'created_at' && $order === 'desc';
        $cacheKey = null;
        $cachedData = null;
        
        if (!$hasFilters && $page === 1 && $isDefaultSort) {
            $cacheKey = 'api_custom_skins_list_' . $limit;
            $cache = Yii::$app->cache;
            $cachedData = $cache->get($cacheKey);
            
            // Если есть кэшированные данные, возвращаем их
            if ($cachedData !== false) {
                return $this->successResponse($cachedData);
            }
        }

        // Если нет кэша или есть фильтры/пагинация/сортировка, строим запрос
        if ($cachedData === false || $cachedData === null || $hasFilters || $page > 1 || !$isDefaultSort) {
            $query = ServerSkin::find()
                ->alias('s')
                ->where(['s.status' => ServerSkin::STATUS_ACTIVE])
                ->with(['user', 'serverSkinCategory', 'creatorUser']);

            // Фильтр по категории
            if ($categoryId) {
                $query->andWhere(['s.server_skin_category_id' => (int)$categoryId]);
            }

            // Поиск по названию
            if ($search) {
                $query->andWhere(['like', 's.name', $search]);
            }

            // Сортировка
            $allowedSorts = ['created_at', 'likes', 'name'];
            if (!in_array($sort, $allowedSorts)) {
                $sort = 'created_at';
            }
            $sortOrder = strtolower($order) === 'asc' ? SORT_ASC : SORT_DESC;
            $query->orderBy(["s.{$sort}" => $sortOrder]);

            $dataProvider = new ActiveDataProvider([
                'query' => $query,
                'pagination' => [
                    'page' => $page - 1, // ActiveDataProvider использует 0-based индексацию
                    'pageSize' => $limit,
                ],
            ]);

            $skins = [];
            foreach ($dataProvider->getModels() as $skin) {
                $skins[] = [
                    'id' => $skin->id,
                    'name' => $skin->name,
                    'skinId' => $skin->skin_id,
                    'image' => $skin->getImagePubUrl(),
                    'image64' => $skin->getImage64PubUrl(),
                    'image150' => $skin->getImage150PubUrl(),
                    'likes' => $skin->likes ?? 0,
                    'category' => $skin->serverSkinCategory ? [
                        'id' => $skin->serverSkinCategory->id,
                        'name' => $skin->serverSkinCategory->name,
                    ] : null,
                    'user' => $skin->user ? [
                        'id' => $skin->user->id,
                        'username' => $skin->user->username,
                        'steamId' => $skin->user->steam_id,
                        'avatar' => $skin->user->getAvatar(),
                    ] : null,
                    'creator' => $skin->creatorUser ? [
                        'id' => $skin->creatorUser->id,
                        'username' => $skin->creatorUser->username,
                        'steamId' => $skin->creatorUser->steam_id,
                        'avatar' => $skin->creatorUser->getAvatar(),
                    ] : null,
                    'createdAt' => $skin->created_at,
                ];
            }

            $pagination = $dataProvider->getPagination();
            $totalPages = $pagination->getPageCount();

            $responseData = [
                'skins' => $skins,
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => $totalPages,
                    'totalCount' => $dataProvider->getTotalCount(),
                    'pageSize' => $limit,
                    'hasMore' => $page < $totalPages,
                ],
            ];

            // Сохраняем в кэш только базовый список (без фильтров, первая страница, дефолтная сортировка)
            if (!$hasFilters && $page === 1 && $isDefaultSort && $cacheKey) {
                $cache->set($cacheKey, $responseData, 300); // 5 минут
            }

            return $this->successResponse($responseData);
        }
    }

    /**
     * Получение списка категорий скинов
     * 
     * @OA\Get(
     *     path="/v1/custom-skins/categories",
     *     operationId="getCustomSkinCategories",
     *     tags={"CustomSkins"},
     *     summary="Получить список категорий скинов",
     *     description="Возвращает все категории скинов",
     *     @OA\Response(
     *         response=200,
     *         description="Список категорий",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionCategories()
    {
        // Кэшируем категории на 1 час
        $cacheKey = 'api_custom_skins_categories';
        $cache = Yii::$app->cache;
        $categoriesData = $cache->get($cacheKey);

        if ($categoriesData === false) {
            $categories = ServerSkinCategory::find()
                ->orderBy(['name' => SORT_ASC])
                ->all();

            $categoriesData = [];
            foreach ($categories as $category) {
                $categoriesData[] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'key' => $category->key,
                ];
            }

            // Сохраняем в кэш на 1 час (3600 секунд)
            $cache->set($cacheKey, $categoriesData, 3600);
        }

        return $this->successResponse($categoriesData);
    }

    /**
     * Создание нового скина
     * 
     * @OA\Post(
     *     path="/v1/custom-skins/create",
     *     operationId="createCustomSkin",
     *     tags={"CustomSkins"},
     *     summary="Создать новый скин",
     *     description="Создает новый скин из мастерской Steam и отправляет на модерацию. Требует авторизации.",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"steam_link"},
     *                 @OA\Property(
     *                     property="steam_link",
     *                     type="string",
     *                     description="Ссылка на скин в мастерской Steam или ID скина (10 цифр)",
     *                     example="https://steamcommunity.com/sharedfiles/filedetails/?id=1234567890"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Скин создан и отправлен на модерацию",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=400, description="Ошибка валидации"),
     *     @OA\Response(response=401, description="Требуется авторизация")
     * )
     */
    public function actionCreate()
    {
        $user = $this->getCurrentUser();

        // Проверяем, что у пользователя есть сервер
        if (empty($user->server)) {
            return $this->errorResponse('NO_SERVER', 'Добавить скин могут только игроки', [], 400);
        }

        $request = Yii::$app->request;
        $steamLink = trim($request->post('steam_link', ''));

        if (empty($steamLink)) {
            return $this->errorResponse('VALIDATION_ERROR', 'Ссылка на скин обязательна', ['steam_link' => 'Ссылка не может быть пустой'], 400);
        }

        // Извлекаем ID из ссылки или используем как ID, если это только цифры
        $skinId = preg_replace("/[^0-9]/", '', $steamLink);
        
        if (empty($skinId) || strlen($skinId) !== 10) {
            return $this->errorResponse('VALIDATION_ERROR', 'Неверно указана ссылка на работу в мастерской Steam', ['steam_link' => 'ID скина должен состоять из 10 цифр'], 400);
        }

        // Проверяем список одобренных скинов
        $approvedSkins = explode(',', Yii::$app->settings->get('custom-skins_approved_list', ''));
        if (in_array($skinId, $approvedSkins)) {
            return $this->errorResponse('SKIN_APPROVED', 'Добавление запрещено: работа одобрена разработчиками и доступна в магазине', [], 400);
        }

        // Проверяем, не добавлен ли уже этот скин
        $exists = ServerSkin::find()
            ->andWhere(['skin_id' => $skinId])
            ->andWhere(['IN', 'status', [ServerSkin::STATUS_ACTIVE, ServerSkin::STATUS_WAIT]])
            ->exists();

        if ($exists) {
            return $this->errorResponse('SKIN_EXISTS', 'Данная работа уже добавлена или ожидает модерации', [], 400);
        }

        // Получаем информацию о скине из Steam API
        $info = ServerSkin::getInfoSkin($skinId);
        if (empty($info)) {
            return $this->errorResponse('STEAM_API_ERROR', 'Не удалось получить информацию о скине из мастерской Steam', [], 400);
        }

        $preview = $info['preview_url'] ?? null;
        $title = $info['title'] ?? null;
        $creatorSteamId = $info['creator'] ?? null;

        if (empty($preview) || empty($title)) {
            return $this->errorResponse('STEAM_API_ERROR', 'Недостаточно информации о скине из мастерской Steam', [], 400);
        }

        // Находим создателя скина
        $creatorUser = null;
        if ($creatorSteamId) {
            $creatorUser = User::findBySteamId($creatorSteamId);
        }

        // Загружаем изображение превью с таймаутом
        $context = stream_context_create([
            'http' => [
                'timeout' => 10, // 10 секунд таймаут
                'ignore_errors' => true,
            ]
        ]);
        $imageData = @file_get_contents($preview, false, $context);
        if ($imageData === false || empty($imageData)) {
            return $this->errorResponse('IMAGE_LOAD_ERROR', 'Не удалось загрузить изображение превью скина', [], 400);
        }
        
        // Проверяем размер файла (максимум 10MB)
        if (strlen($imageData) > 10 * 1024 * 1024) {
            return $this->errorResponse('IMAGE_TOO_LARGE', 'Изображение слишком большое (максимум 10MB)', [], 400);
        }

        // Создаем модель формы для обработки
        $model = new ServerSkinForm();
        $model->steam_link = $steamLink;
        $model->user_id = $user->id;
        $model->status = ServerSkin::STATUS_WAIT;
        $model->created_at = date('Y-m-d H:i:s');
        $model->name = $title;
        $model->skin_id = $skinId;
        
        // Определяем категорию из тегов
        $tag = $info['tags'][0]['tag'] ?? null;
        if ($tag == 'Version3') {
            $tag = $info['tags'][1]['tag'] ?? null;
            if ($tag == 'Skin') {
                $tag = $info['tags'][2]['tag'] ?? null;
            }
        } elseif ($tag == 'Skin') {
            $tag = $info['tags'][1]['tag'] ?? null;
            if ($tag == 'Version3') {
                $tag = $info['tags'][2]['tag'] ?? null;
            }
        }
        
        $category = null;
        if ($tag) {
            $category = ServerSkinCategory::getCategory($tag);
        }
        $model->server_skin_category_id = $category ? $category->id : null;
        
        if ($creatorUser) {
            $model->creator_user_id = $creatorUser->id;
        }

        // Загружаем изображение
        $imageResult = $this->loadSkinImage($imageData);
        if (empty($imageResult) || !isset($imageResult['image'])) {
            return $this->errorResponse('IMAGE_PROCESS_ERROR', 'Ошибка обработки изображения превью', [], 400);
        }

        $model->image = $imageResult['image'];
        $model->image_64 = $imageResult['image_64'];
        $model->image_150 = $imageResult['image_150'];

        // Сохраняем скин
        if (!$model->save()) {
            return $this->errorResponse('SAVE_ERROR', 'Ошибка при сохранении скина', $model->errors, 500);
        }

        // Отправляем уведомление в Telegram (если настроено)
        try {
            Yii::$app->telegramSupport->sendMessage(
                "👕 Новый скин отправлен на модерацию!",
                [
                    [
                        'text' => '🟢 Принять',
                        'callback_data' => json_encode([
                            'action'   => 'success-skin',
                            'skin_id'  => $skinId,
                        ])
                    ],
                    [
                        'text' => '🔴 Отклонить',
                        'callback_data' => json_encode([
                            'action'   => 'reject-skin',
                            'skin_id'  => $skinId,
                        ])
                    ]
                ],
                $model->getImagePubUrl()
            );
        } catch (\Exception $e) {
            Yii::error('Error sending telegram notification: ' . $e->getMessage(), __METHOD__);
        }

        try {
            Yii::$app->personalBotTelegram->sendMessage($user->telegram_chat_id, "👕 Скин отправлен на модерацию!");
        } catch (\Exception $e) {
            Yii::error('Error sending personal telegram notification: ' . $e->getMessage(), __METHOD__);
        }

        return $this->successResponse([
            'id' => $model->id,
            'name' => $model->name,
            'skinId' => $model->skin_id,
            'status' => $model->status,
            'image' => $model->getImagePubUrl(),
            'image64' => $model->getImage64PubUrl(),
            'image150' => $model->getImage150PubUrl(),
            'category' => $model->serverSkinCategory ? [
                'id' => $model->serverSkinCategory->id,
                'name' => $model->serverSkinCategory->name,
            ] : null,
            'createdAt' => $model->created_at,
        ], [], 201);
    }

    /**
     * Загрузка и обработка изображения скина
     * 
     * @param string $imageData Данные изображения
     * @return array|null Массив с путями к изображениям или null при ошибке
     */
    protected function loadSkinImage($imageData)
    {
        if (empty($imageData)) {
            return null;
        }
        
        $s3Api = Yii::$app->s3Api;
        $tempDir = sys_get_temp_dir();
        
        // Проверяем, что временная директория существует и доступна для записи
        if (!is_dir($tempDir) || !is_writable($tempDir)) {
            Yii::error('Temporary directory is not writable: ' . $tempDir, __METHOD__);
            return null;
        }
        
        // Используем временный ID для имени файла (будет заменен после сохранения)
        $filename = uniqid('skin_') . "_" . md5(time()) . ".png";
        
        // Определяем формат изображения по содержимому
        $imageInfo = @getimagesizefromstring($imageData);
        if ($imageInfo === false) {
            Yii::error('Invalid image data provided to loadSkinImage', __METHOD__);
            return null;
        }
        
        // Быстрая проверка размеров изображения (максимум 10000x10000)
        if (isset($imageInfo[0]) && isset($imageInfo[1])) {
            if ($imageInfo[0] > 10000 || $imageInfo[1] > 10000) {
                Yii::error('Image dimensions too large: ' . $imageInfo[0] . 'x' . $imageInfo[1], __METHOD__);
                return null;
            }
            if ($imageInfo[0] <= 0 || $imageInfo[1] <= 0) {
                Yii::error('Invalid image dimensions: ' . $imageInfo[0] . 'x' . $imageInfo[1], __METHOD__);
                return null;
            }
        }
        
        // Определяем расширение на основе MIME типа
        $extension = 'png';
        if (!empty($imageInfo['mime'])) {
            $mimeToExt = [
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            if (isset($mimeToExt[$imageInfo['mime']])) {
                $extension = $mimeToExt[$imageInfo['mime']];
            }
        }
        
        // Сохраняем оригинал во временный файл с правильным расширением
        $tempOriginal = $tempDir . '/' . uniqid('skin_orig_') . '.' . $extension;
        $bytesWritten = file_put_contents($tempOriginal, $imageData);
        
        // Проверяем, что файл был успешно записан и существует
        if ($bytesWritten === false || !file_exists($tempOriginal) || !is_readable($tempOriginal)) {
            Yii::error('Failed to write temporary image file: ' . $tempOriginal, __METHOD__);
            return null;
        }
        
        // Проверяем, что это валидное изображение
        $imageSize = @getimagesize($tempOriginal);
        if ($imageSize === false) {
            Yii::error('Invalid image data in temporary file: ' . $tempOriginal, __METHOD__);
            @unlink($tempOriginal);
            return null;
        }
        
        // Быстрая проверка размера файла на диске
        $fileSize = filesize($tempOriginal);
        if ($fileSize === false || $fileSize > 10 * 1024 * 1024) {
            Yii::error('Image file too large: ' . $fileSize . ' bytes', __METHOD__);
            @unlink($tempOriginal);
            return null;
        }
        
        // Создаем превью разных размеров во временных файлах
        $temp200 = $tempDir . '/' . uniqid('skin_200_') . '.png';
        $temp64 = $tempDir . '/' . uniqid('skin_64_') . '.png';
        $temp150 = $tempDir . '/' . uniqid('skin_150_') . '.png';
        
        // Создаем ресайзы по одному, сразу проверяя результат для быстрого возврата ошибки
        $resize200 = DropImage::resizeImage($tempOriginal, $temp200, 200);
        if (!$resize200 || !file_exists($temp200)) {
            Yii::error('Failed to create 200px resize for server skin', __METHOD__);
            @unlink($tempOriginal);
            return null;
        }
        
        $resize64 = DropImage::resizeImage($tempOriginal, $temp64, 64);
        if (!$resize64 || !file_exists($temp64)) {
            Yii::error('Failed to create 64px resize for server skin', __METHOD__);
            @unlink($tempOriginal);
            @unlink($temp200);
            return null;
        }
        
        $resize150 = DropImage::resizeImage($tempOriginal, $temp150, 150);
        if (!$resize150 || !file_exists($temp150)) {
            Yii::error('Failed to create 150px resize for server skin', __METHOD__);
            @unlink($tempOriginal);
            @unlink($temp200);
            @unlink($temp64);
            return null;
        }
        
        // Загружаем все версии в S3
        $s3KeyOriginal = 'uploads/server-skin/' . $filename;
        $s3Key200 = 'uploads/server-skin-x150/' . $filename;
        $s3Key64 = 'uploads/server-skin-64/' . $filename;
        $s3Key150 = 'uploads/server-skin-150/' . $filename;
        
        $s3ResultOriginal = $s3Api->putFile($s3KeyOriginal, file_get_contents($tempOriginal), 'image/png');
        $s3Result200 = $s3Api->putFile($s3Key200, file_get_contents($temp200), 'image/png');
        $s3Result64 = $s3Api->putFile($s3Key64, file_get_contents($temp64), 'image/png');
        $s3Result150 = $s3Api->putFile($s3Key150, file_get_contents($temp150), 'image/png');
        
        // Удаляем временные файлы
        @unlink($tempOriginal);
        @unlink($temp200);
        @unlink($temp64);
        @unlink($temp150);
        
        if ($s3ResultOriginal === false || $s3Result200 === false || $s3Result64 === false || $s3Result150 === false) {
            Yii::error('Error uploading server skin image to S3', __METHOD__);
            return null;
        }
        
        return [
            'image' => '/uploads/server-skin-x150/' . $filename,
            'image_64' => '/server-skin-64/' . $filename,
            'image_150' => '/server-skin-150/' . $filename,
        ];
    }

    /**
     * Получение списка пользователей, поставивших лайк скину
     * 
     * @OA\Get(
     *     path="/v1/custom-skins/{id}/likes",
     *     operationId="getCustomSkinLikes",
     *     tags={"CustomSkins"},
     *     summary="Получить список пользователей, поставивших лайк",
     *     description="Возвращает список пользователей, которые поставили лайк скину, с пагинацией",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID скина",
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
     *     @OA\Response(response=404, description="Скин не найден")
     * )
     */
    public function actionLikes($id)
    {
        $request = Yii::$app->request;
        $page = (int)$request->get('page', 1);
        $limit = (int)$request->get('limit', 20);

        // Проверяем существование скина
        $skin = ServerSkin::find()
            ->where(['id' => $id, 'status' => ServerSkin::STATUS_ACTIVE])
            ->one();

        if (!$skin) {
            return $this->errorResponse('SKIN_NOT_FOUND', 'Скин не найден', [], 404);
        }

        $query = ServerSkinLike::find()
            ->where(['server_skin_id' => $id, 'type' => ServerSkinLike::TYPE_LIKE])
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
     * Постановка/снятие лайка скину
     * 
     * @OA\Post(
     *     path="/v1/custom-skins/{id}/like",
     *     operationId="likeCustomSkin",
     *     tags={"CustomSkins"},
     *     summary="Поставить или убрать лайк скину",
     *     description="Переключает лайк скину. Требует авторизации.",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID скина",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Лайк переключен",
     *         @OA\MediaType(mediaType="application/json")
     *     ),
     *     @OA\Response(response=401, description="Требуется авторизация"),
     *     @OA\Response(response=404, description="Скин не найден")
     * )
     */
    public function actionLike($id)
    {
        $user = $this->getCurrentUser();

        $skin = ServerSkin::find()
            ->where(['id' => $id, 'status' => ServerSkin::STATUS_ACTIVE])
            ->one();

        if (!$skin) {
            return $this->errorResponse('SKIN_NOT_FOUND', 'Скин не найден', [], 404);
        }

        // Проверяем, есть ли уже лайк от этого пользователя
        $userLike = ServerSkinLike::find()
            ->where(['server_skin_id' => $id, 'user_id' => $user->id, 'type' => ServerSkinLike::TYPE_LIKE])
            ->one();

        if ($userLike) {
            // Убираем лайк
            $userLike->delete();
            $skin->likes = max(0, $skin->likes - 1);
            $skin->save(false);
            $isLiked = false;
        } else {
            // Ставим лайк
            $like = new ServerSkinLike();
            $like->user_id = $user->id;
            $like->server_skin_id = $id;
            $like->type = ServerSkinLike::TYPE_LIKE;
            $like->created_at = date('Y-m-d H:i:s');
            
            if ($like->save()) {
                $skin->likes += 1;
                $skin->save(false);
                $isLiked = true;
            } else {
                return $this->errorResponse('SAVE_ERROR', 'Ошибка при сохранении лайка', $like->errors, 500);
            }
        }

        return $this->successResponse([
            'isLiked' => $isLiked,
            'likes' => $skin->likes,
        ]);
    }
}

