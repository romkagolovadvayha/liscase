<?php

namespace api\controllers\v1;

use Yii;
use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\user\User;
use yii2mod\comments\models\CommentModel;
use api\components\jwt\JwtAuthFilter;
use api\components\jwt\JwtService;
use yii\data\ActiveDataProvider;
use yii\db\Query;
use OpenApi\Annotations as OA;

/**
 * Контроллер для работы с блогом
 * 
 * @package api\controllers\v1
 * @OA\Tag(name="Blog")
 */
class BlogController extends BaseApiController
{
    /**
     * @var bool|null Кэш существования таблицы comment_like
     */
    private static $commentLikeTableExists = null;

    /**
     * Проверяет существование таблицы comment_like (с кэшем).
     * @return bool
     */
    private function commentLikeTableExists(): bool
    {
        if (self::$commentLikeTableExists === null) {
            self::$commentLikeTableExists = Yii::$app->db->schema->getTableSchema('comment_like', true) !== null;
        }
        return self::$commentLikeTableExists;
    }

    /**
     * Настройка behaviors
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // JWT авторизация требуется для создания комментариев и лайков
        $behaviors['authenticator'] = [
            'class' => JwtAuthFilter::class,
            'except' => ['index', 'categories', 'view', 'similar', 'comments', 'options'], // Публичные методы
        ];

        return $behaviors;
    }

    /**
     * Получение списка постов блога
     * 
     * @OA\Get(
     *     path="/v1/blog",
     *     operationId="getBlogPosts",
     *     tags={"Blog"},
     *     summary="Получить список постов блога",
     *     description="Возвращает список постов блога с фильтрацией и пагинацией",
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Количество постов на странице",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Номер страницы",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
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
     *         description="Поле для сортировки (created_at, views)",
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
     *         description="Список постов",
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
            $cacheKey = 'api_blog_list_' . $limit;
            $cache = Yii::$app->cache;
            $cachedData = $cache->get($cacheKey);
            
            // Если есть кэшированные данные, возвращаем их
            if ($cachedData !== false) {
                return $this->successResponse($cachedData);
            }
        }

        // Если нет кэша или есть фильтры/пагинация/сортировка, строим запрос
        if ($cachedData === false || $cachedData === null || $hasFilters || $page > 1 || !$isDefaultSort) {
            $query = Blog::find()
                ->alias('b')
                ->where(['b.status' => Blog::STATUS_ACTIVE])
                ->with(['blogCategory', 'blogImages', 'comments']);

        // Фильтр по категории
        if ($categoryId) {
            $query->andWhere(['b.blog_category_id' => (int)$categoryId]);
        }

        // Поиск по названию
        if ($search) {
            $query->andWhere(['like', 'b.name', $search]);
        }

        // Сортировка
        $allowedSorts = ['created_at', 'views', 'name'];
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

        $posts = [];
        foreach ($dataProvider->getModels() as $blog) {
            $imageUrl = null;
            $blogImages = $blog->blogImages; // Получаем коллекцию
            if (!empty($blogImages) && is_array($blogImages) && count($blogImages) > 0) {
                $firstImage = reset($blogImages);
                if ($firstImage) {
                    $imageUrl = $firstImage->getPublicUrl();
                }
            } elseif (!empty($blogImages) && is_object($blogImages)) {
                // Если это ActiveQuery, получаем все записи
                $images = $blogImages->all();
                if (!empty($images)) {
                    $firstImage = reset($images);
                    if ($firstImage) {
                        $imageUrl = $firstImage->getPublicUrl();
                    }
                }
            }

            $categoryUrl = '';
            if ($blog->blogCategory) {
                $categoryUrl = $blog->blogCategory->link_name;
                if ($blog->blogCategory->blog_category_id) {
                    $parentCategory = BlogCategory::findOne($blog->blogCategory->blog_category_id);
                    if ($parentCategory) {
                        $categoryUrl = $parentCategory->link_name . '/' . $categoryUrl;
                    }
                }
            }

            // Подсчитываем комментарии
            $commentsCount = 0;
            $comments = $blog->comments; // Получаем коллекцию
            if (!empty($comments)) {
                if (is_array($comments)) {
                    $commentsCount = count($comments);
                } elseif (is_object($comments)) {
                    // Если это ActiveQuery, получаем количество
                    $commentsCount = $comments->count();
                }
            }

            // Обрабатываем контент для замены ссылок на изображения на S3 URL
            $processedContent = $blog->processContentWithS3Images($blog->content);

            $posts[] = [
                'id' => $blog->id,
                'title' => $blog->name,
                'description' => $blog->description,
                'content' => $processedContent,
                'image' => $imageUrl,
                'views' => $blog->views ?? 0,
                'commentsCount' => $commentsCount,
                'linkName' => $blog->link_name,
                'url' => $categoryUrl ? "/posts/{$categoryUrl}/post-{$blog->link_name}" : "/posts/post-{$blog->link_name}",
                'category' => $blog->blogCategory ? [
                    'id' => $blog->blogCategory->id,
                    'name' => $blog->blogCategory->name,
                    'linkName' => $blog->blogCategory->link_name,
                ] : null,
                'createdAt' => $blog->created_at,
            ];
        }

        $pagination = $dataProvider->getPagination();
        $totalPages = $pagination->getPageCount();

        $responseData = [
            'posts' => $posts,
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
     * Получение категорий блога
     * 
     * @OA\Get(
     *     path="/v1/blog/categories",
     *     operationId="getBlogCategories",
     *     tags={"Blog"},
     *     summary="Получить категории блога",
     *     description="Возвращает все категории блога с их дочерними категориями",
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
        $cacheKey = 'api_blog_categories';
        $cache = Yii::$app->cache;
        $categories = $cache->get($cacheKey);

        if ($categories === false) {
            // Получаем все родительские категории (без parent_id)
            $parentCategories = BlogCategory::find()
                ->where(['blog_category_id' => null])
                ->andWhere(['status' => BlogCategory::STATUS_ACTIVE])
                ->orderBy(['name' => SORT_ASC])
                ->all();

            $categories = [];
            foreach ($parentCategories as $parentCategory) {
                // Получаем дочерние категории
                $children = BlogCategory::find()
                    ->where(['blog_category_id' => $parentCategory->id])
                    ->andWhere(['status' => BlogCategory::STATUS_ACTIVE])
                    ->orderBy(['name' => SORT_ASC])
                    ->all();

                $childrenData = [];
                foreach ($children as $child) {
                    $childrenData[] = [
                        'id' => $child->id,
                        'name' => $child->name,
                        'linkName' => $child->link_name,
                        'description' => $child->description,
                    ];
                }

                $categories[] = [
                    'id' => $parentCategory->id,
                    'name' => $parentCategory->name,
                    'linkName' => $parentCategory->link_name,
                    'description' => $parentCategory->description,
                    'children' => $childrenData,
                ];
            }

            // Сохраняем в кэш на 1 час (3600 секунд)
            $cache->set($cacheKey, $categories, 3600);
        }

        return $this->successResponse($categories);
    }

    /**
     * Получение похожих постов блога
     * 
     * @OA\Get(
     *     path="/v1/blog/{linkName}/similar",
     *     operationId="getSimilarBlogPosts",
     *     tags={"Blog"},
     *     summary="Получить похожие посты блога",
     *     description="Возвращает список похожих постов на основе полнотекстового поиска",
     *     @OA\Parameter(
     *         name="linkName",
     *         in="path",
     *         description="link_name поста",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Количество похожих постов",
     *         required=false,
     *         @OA\Schema(type="integer", default=5)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список похожих постов",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionSimilar($linkName)
    {
        $limit = (int)Yii::$app->request->get('limit', 5);
        
        $blog = Blog::find()
            ->where(['link_name' => $linkName, 'status' => Blog::STATUS_ACTIVE])
            ->one();

        if (!$blog) {
            return $this->errorResponse('BLOG_NOT_FOUND', 'Пост не найден', [], 404);
        }

        $similarPosts = Blog::getSimilarPostsFulltext($blog, $limit);

        // Загружаем полные данные для похожих постов с изображениями и категориями
        $similarIds = array_map(function($post) {
            return $post->id;
        }, $similarPosts);
        
        $similarPostsFull = Blog::find()
            ->where(['id' => $similarIds])
            ->with(['blogImages', 'blogCategory'])
            ->indexBy('id')
            ->all();

        $posts = [];
        foreach ($similarPosts as $similarBlog) {
            // Получаем полные данные с изображениями
            $similarBlogFull = $similarPostsFull[$similarBlog->id] ?? null;
            if (!$similarBlogFull) {
                continue;
            }
            
            $imageUrl = null;
            $blogImages = $similarBlogFull->blogImages;
            if (!empty($blogImages)) {
                if (is_array($blogImages) && count($blogImages) > 0) {
                    $firstImage = reset($blogImages);
                    if ($firstImage) {
                        $imageUrl = $firstImage->getPublicUrl();
                    }
                } elseif (is_object($blogImages)) {
                    $images = $blogImages->all();
                    if (!empty($images)) {
                        $firstImage = reset($images);
                        if ($firstImage) {
                            $imageUrl = $firstImage->getPublicUrl();
                        }
                    }
                }
            }

            $categoryUrl = '';
            if ($similarBlogFull->blogCategory) {
                $categoryUrl = $similarBlogFull->blogCategory->link_name;
                if ($similarBlogFull->blogCategory->blog_category_id) {
                    $parentCategory = BlogCategory::findOne($similarBlogFull->blogCategory->blog_category_id);
                    if ($parentCategory) {
                        $categoryUrl = $parentCategory->link_name . '/' . $categoryUrl;
                    }
                }
            }

            $posts[] = [
                'id' => $similarBlogFull->id,
                'title' => $similarBlogFull->name,
                'description' => $similarBlogFull->description,
                'image' => $imageUrl,
                'linkName' => $similarBlogFull->link_name,
                'url' => $categoryUrl ? "/posts/{$categoryUrl}/post-{$similarBlogFull->link_name}" : "/posts/post-{$similarBlogFull->link_name}",
                'category' => $similarBlogFull->blogCategory ? [
                    'id' => $similarBlogFull->blogCategory->id,
                    'name' => $similarBlogFull->blogCategory->name,
                    'linkName' => $similarBlogFull->blogCategory->link_name,
                ] : null,
                'createdAt' => $similarBlogFull->created_at,
            ];
        }

        return $this->successResponse([
            'posts' => $posts,
        ]);
    }

    /**
     * Получение комментариев поста блога
     * 
     * @OA\Get(
     *     path="/v1/blog/{linkName}/comments",
     *     operationId="getBlogComments",
     *     tags={"Blog"},
     *     summary="Получить комментарии поста блога",
     *     description="Возвращает список комментариев поста блога",
     *     @OA\Parameter(
     *         name="linkName",
     *         in="path",
     *         description="link_name поста",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Список комментариев",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionComments($linkName)
    {
        // Если это POST запрос, обрабатываем создание комментария
        if (Yii::$app->request->isPost) {
            // POST запрос требует авторизации - авторизуем пользователя из токена
            try {
                $jwtService = Yii::$app->jwt;
                $token = $jwtService->extractTokenFromRequest(Yii::$app->request);
                
                if (empty($token)) {
                    return $this->errorResponse('UNAUTHORIZED', 'Требуется авторизация', [], 401);
                }
                
                // Валидация токена
                $payload = $jwtService->validateToken($token);
                
                // Поиск пользователя
                $userId = $jwtService->getUserId($payload);
                $steamId = $jwtService->getSteamId($payload);
                
                $user = null;
                if ($userId) {
                    $user = User::findIdentity($userId);
                }
                if (!$user && $steamId) {
                    $user = User::find()->where(['steam_id' => $steamId])->one();
                }
                
                if (!$user) {
                    return $this->errorResponse('UNAUTHORIZED', 'Пользователь не найден', [], 401);
                }
                
                // Авторизация пользователя
                Yii::$app->user->login($user, 0);
                
            } catch (\Throwable $e) {
                return $this->errorResponse('UNAUTHORIZED', 'Требуется авторизация', [], 401);
            }
            
            // Вызываем логику создания комментария
            return $this->actionCreateComment($linkName);
        }

        // GET запрос - возвращаем список комментариев
        $blog = Blog::find()
            ->where(['link_name' => $linkName, 'status' => Blog::STATUS_ACTIVE])
            ->one();

        if (!$blog) {
            return $this->errorResponse('BLOG_NOT_FOUND', 'Пост не найден', [], 404);
        }

        $entityHash = hash('crc32', Blog::class);
        $userId = Yii::$app->user->isGuest ? null : Yii::$app->user->id;

        // Получаем корневые комментарии (parentId = null)
        $comments = CommentModel::find()
            ->where(['entity' => $entityHash, 'entityId' => $blog->id, 'status' => 1])
            ->andWhere(['parentId' => null])
            ->orderBy(['createdAt' => SORT_DESC])
            ->all();

        $commentsData = [];
        foreach ($comments as $comment) {
            $commentsData[] = $this->formatComment($comment, $userId);
        }

        return $this->successResponse([
            'comments' => $commentsData,
            'totalCount' => CommentModel::find()
                ->where(['entity' => $entityHash, 'entityId' => $blog->id, 'status' => 1])
                ->count(),
        ]);
    }

    /**
     * Создание комментария к посту блога
     * 
     * @OA\Post(
     *     path="/v1/blog/{linkName}/comments",
     *     operationId="createBlogComment",
     *     tags={"Blog"},
     *     summary="Создать комментарий к посту блога",
     *     description="Создает новый комментарий к посту блога",
     *     @OA\Parameter(
     *         name="linkName",
     *         in="path",
     *         description="link_name поста",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"content"},
     *                 @OA\Property(property="content", type="string", description="Текст комментария"),
     *                 @OA\Property(property="parentId", type="integer", description="ID родительского комментария (для ответов)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Комментарий создан",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionCreateComment($linkName)
    {
        $user = $this->getCurrentUser();
        
        $blog = Blog::find()
            ->where(['link_name' => $linkName, 'status' => Blog::STATUS_ACTIVE])
            ->one();

        if (!$blog) {
            return $this->errorResponse('BLOG_NOT_FOUND', 'Пост не найден', [], 404);
        }

        $post = Yii::$app->request->post();
        $content = trim($post['content'] ?? '');
        $parentId = !empty($post['parentId']) ? (int)$post['parentId'] : null;

        if (empty($content)) {
            return $this->errorResponse('INVALID_DATA', 'Текст комментария не может быть пустым', [], 400);
        }

        if (strlen($content) > 5000) {
            return $this->errorResponse('INVALID_DATA', 'Текст комментария слишком длинный (максимум 5000 символов)', [], 400);
        }

        // Проверяем родительский комментарий, если указан
        if ($parentId) {
            $parentComment = CommentModel::find()
                ->where(['id' => $parentId, 'entity' => hash('crc32', Blog::class), 'entityId' => $blog->id, 'status' => 1])
                ->one();
            
            if (!$parentComment) {
                return $this->errorResponse('PARENT_COMMENT_NOT_FOUND', 'Родительский комментарий не найден', [], 404);
            }

            // Проверяем максимальный уровень вложенности (максимум 2 уровня)
            if ($parentComment->level >= 2) {
                return $this->errorResponse('MAX_LEVEL_REACHED', 'Достигнут максимальный уровень вложенности комментариев', [], 400);
            }
        }

        $entityHash = hash('crc32', Blog::class);
        
        $comment = new CommentModel();
        $comment->entity = $entityHash;
        $comment->entityId = $blog->id;
        $comment->content = $content;
        $comment->createdBy = $user->id;
        $comment->status = 1; // Активный комментарий
        $comment->createdAt = time();
        $comment->updatedAt = time();

        // Используем методы AdjacencyListBehavior для создания узлов дерева
        if ($parentId && $parentComment) {
            // Создаем дочерний комментарий
            $comment->appendTo($parentComment);
        } else {
            // Создаем корневой комментарий
            $comment->makeRoot();
        }

        if (!$comment->save()) {
            Yii::error('Error saving comment: ' . json_encode($comment->errors), 'api');
            return $this->errorResponse('SAVE_ERROR', 'Ошибка при сохранении комментария', $comment->errors, 400);
        }

        // Загружаем полные данные комментария
        $savedComment = CommentModel::find()
            ->where(['id' => $comment->id])
            ->one();

        if (!$savedComment) {
            Yii::error('Comment not found after save, id: ' . $comment->id, 'api');
            return $this->errorResponse('COMMENT_NOT_FOUND', 'Комментарий не найден после сохранения', [], 500);
        }

        try {
            return $this->successResponse([
                'comment' => $this->formatComment($savedComment, $user->id),
            ]);
        } catch (\Throwable $e) {
            Yii::error('Error formatting comment: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'api');
            return $this->errorResponse('FORMAT_ERROR', 'Ошибка при форматировании комментария: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Лайк/дизлайк комментария
     * 
     * @OA\Post(
     *     path="/v1/blog/comments/{id}/like",
     *     operationId="likeBlogComment",
     *     tags={"Blog"},
     *     summary="Лайк/дизлайк комментария",
     *     description="Переключает лайк комментария",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID комментария",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Лайк переключен",
     *         @OA\MediaType(mediaType="application/json")
     *     )
     * )
     */
    public function actionLikeComment($id)
    {
        $user = $this->getCurrentUser();
        
        $comment = CommentModel::find()
            ->where(['id' => $id, 'status' => 1])
            ->one();

        if (!$comment) {
            return $this->errorResponse('COMMENT_NOT_FOUND', 'Комментарий не найден', [], 404);
        }

        if (!$this->commentLikeTableExists()) {
            return $this->errorResponse('FEATURE_NOT_AVAILABLE', 'Функция лайков комментариев недоступна', [], 501);
        }

        // Проверяем, есть ли уже лайк
        $likeExists = Yii::$app->db->createCommand('SELECT id FROM comment_like WHERE comment_id = :comment_id AND user_id = :user_id', [
            ':comment_id' => $id,
            ':user_id' => $user->id
        ])->queryOne();

        if ($likeExists) {
            // Удаляем лайк
            try {
                Yii::$app->db->createCommand()
                    ->delete('comment_like', ['comment_id' => $id, 'user_id' => $user->id])
                    ->execute();
                
                $isLiked = false;
            } catch (\Throwable $e) {
                Yii::error('Error deleting comment like: ' . $e->getMessage(), 'api');
                return $this->errorResponse('SERVER_ERROR', 'Ошибка при удалении лайка', [], 500);
            }
        } else {
            // Добавляем лайк
            try {
                Yii::$app->db->createCommand()
                    ->insert('comment_like', [
                        'comment_id' => $id,
                        'user_id' => $user->id,
                        'created_at' => time(),
                    ])
                    ->execute();
                
                $isLiked = true;
            } catch (\Throwable $e) {
                Yii::error('Error inserting comment like: ' . $e->getMessage(), 'api');
                return $this->errorResponse('SERVER_ERROR', 'Ошибка при добавлении лайка', [], 500);
            }
        }

        // Получаем количество лайков
        $likesCount = 0;
        try {
            $likesCount = (int)Yii::$app->db->createCommand('SELECT COUNT(*) FROM comment_like WHERE comment_id = :id', [':id' => $id])
                ->queryScalar();
        } catch (\Throwable $e) {
            Yii::error('Error getting comment likes count: ' . $e->getMessage(), 'api');
            $likesCount = 0;
        }

        return $this->successResponse([
            'isLiked' => $isLiked,
            'likesCount' => $likesCount,
        ]);
    }

    /**
     * Форматирование комментария для API
     * 
     * @param CommentModel $comment
     * @param int|null $userId
     * @return array
     */
    protected function formatComment(CommentModel $comment, $userId = null)
    {
        // Получаем пользователя напрямую через createdBy (это ID пользователя, а не отношение)
        $user = null;
        $avatar = '';
        $username = '';
        $steamId = '';
        $userCommentId = null;
        
        if ($comment->createdBy) {
            $user = User::find()
                ->where(['id' => $comment->createdBy])
                ->with('userProfile')
                ->one();
            
            if ($user) {
                $userCommentId = $user->id;
                $username = $user->username ?? '';
                $steamId = $user->steam_id ?? '';
                try {
                    $avatar = $user->getAvatar() ?? '';
                } catch (\Throwable $e) {
                    Yii::error('Error getting avatar: ' . $e->getMessage(), 'api');
                }
            }
        }

        // Получаем количество лайков (только если таблица comment_like существует)
        $likesCount = 0;
        $isLiked = false;
        if ($this->commentLikeTableExists()) {
            try {
                $likesCount = (int)Yii::$app->db->createCommand('SELECT COUNT(*) FROM comment_like WHERE comment_id = :id', [':id' => $comment->id])
                    ->queryScalar();
                if ($userId) {
                    $likeExists = Yii::$app->db->createCommand('SELECT id FROM comment_like WHERE comment_id = :comment_id AND user_id = :user_id', [
                        ':comment_id' => $comment->id,
                        ':user_id' => $userId
                    ])->queryOne();
                    $isLiked = (bool)$likeExists;
                }
            } catch (\Throwable $e) {
                Yii::error('Error getting comment likes: ' . $e->getMessage(), 'api');
            }
        }

        // Получаем дочерние комментарии
        $children = CommentModel::find()
            ->where(['parentId' => $comment->id, 'status' => 1])
            ->orderBy(['createdAt' => SORT_ASC])
            ->all();

        $childrenData = [];
        foreach ($children as $child) {
            $childrenData[] = $this->formatComment($child, $userId);
        }

        // Проверяем, можно ли ответить на этот комментарий (максимум 2 уровня вложенности)
        $canReply = $comment->level < 2;

        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'parentId' => $comment->parentId,
            'level' => $comment->level,
            'userId' => $userCommentId,
            'username' => $username,
            'steamId' => $steamId,
            'avatar' => $avatar,
            'createdAt' => $comment->createdAt,
            'updatedAt' => $comment->updatedAt,
            'likesCount' => $likesCount,
            'isLiked' => $isLiked,
            'canReply' => $canReply,
            'replies' => $childrenData,
        ];
    }
}

