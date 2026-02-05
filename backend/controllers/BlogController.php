<?php

namespace backend\controllers;

use backend\forms\blog\BlogForm;
use common\components\helpers\Role;
use common\components\queue\openAi\GenPostJob;
use common\components\queue\openAi\GenPostsJob;
use common\models\blog\Blog;
use backend\models\blog\BlogSearch;
use common\models\blog\BlogCategory;
use backend\components\BackendController;
use yii\base\BaseObject;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use common\components\vk\VkApiHelper;
use common\components\openAi\OpenAiVkPost;
use common\components\openAi\OpenAiTelegramPost;
use common\components\telegram\TelegramChannelHelper;
use yii\helpers\Html;
use Yii;

/**
 * BlogController implements the CRUD actions for Blog model.
 */
class BlogController extends BackendController
{

    /**
     * @return array
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR, Role::ROLE_SUPPORT],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Lists all Blog models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new BlogSearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionGeneratePost($postId)
    {
        $cacheKey  = 'actionGenerate_Post_' . $postId;
        $cacheData = Yii::$app->cache->get($cacheKey);
        if (!empty($cacheData)) {
            Yii::$app->session->addFlash('success', 'Процесс генерации уже запущен, ожидайте.');
            return $this->redirect(['/blog/view', 'id' => $postId]);
        }
        Yii::$app->queueOpenAi->push(new GenPostJob([
            'postId' => $postId
        ]));
        Yii::$app->cache->set($cacheKey, $cacheKey, 6 * 60 * 60);
        return $this->redirect(['/blog/view', 'id' => $postId]);
    }

    public function actionGenerate($categoryId = null)
    {
        $categoryIds = [];
        if (!empty($categoryId)) {
            $categoryIds[] = $categoryId;
        } else {
            /** @var BlogCategory[] $categories */
            $categories = BlogCategory::find()->all();
            foreach ($categories as $item) {
                $categoryIds[] = $item->id;
            }
        }

        foreach ($categoryIds as $categoryId) {
            $cacheKey  = 'actionGenerate_Posts_' . $categoryId;
            $cacheData = Yii::$app->cache->get($cacheKey);
            if (!empty($cacheData)) {
                Yii::$app->session->addFlash('success', 'Процесс генерации уже запущен, ожидайте.');

                return $this->redirect(['/blog-category/index']);
            }
            Yii::$app->cache->delete('actionGenerateError_Posts_' . $categoryId);

            Yii::$app->queueOpenAi->push(new GenPostsJob([
                'categoryId' => $categoryId,
            ]));

            Yii::$app->session->addFlash('success', 'Процесс генерации запущен, ожидайте.');
            Yii::$app->cache->set($cacheKey, $cacheKey, 6 * 60 * 60);
            Yii::$app->cache->set('actionGeneratePosts', $cacheKey, 6 * 60 * 60);
        }
        return $this->redirect(['/blog-category/index']);
    }

    /**
     * Displays a single Blog model.
     * @param int $id ID
     * @return string
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Blog model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new BlogForm();

        if ($this->request->isPost) {
            if ($model->load($this->request->post()) && $model->saveRecord()) {
                // Очищаем кэш списка блога
                $this->clearBlogCache();
                return $this->redirect(['view', 'id' => $model->id]);
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Blog model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id ID
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($this->request->isPost && $model->load($this->request->post()) && $model->saveRecord()) {
            // Очищаем кэш списка блога
            $this->clearBlogCache();
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Blog model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();
        
        // Очищаем кэш списка блога
        $this->clearBlogCache();

        return $this->redirect(['index']);
    }

    /**
     * Очистка кэша блога
     */
    protected function clearBlogCache()
    {
        // Очищаем кэш списка блога (все возможные варианты limit)
        for ($limit = 10; $limit <= 50; $limit += 10) {
            Yii::$app->cache->delete('api_blog_list_' . $limit);
        }
    }

    /**
     * Finds the Blog model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return BlogForm the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = BlogForm::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionTinymceUpload()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $file = \yii\web\UploadedFile::getInstanceByName('file');
        if (!$file) return ['error' => 'No file uploaded'];

        $allowed = ['image/png','image/jpeg','image/gif','image/webp','image/svg+xml'];
        if (!in_array($file->type, $allowed, true)) return ['error' => 'Файл не является изображением'];
        if ($file->size > 5 * 1024 * 1024) return ['error' => 'Файл слишком большого размера'];

        $safe = preg_replace('~[^a-z0-9\.\-_]~i', '_', $file->name);
        $fname = date('Ymd_His') . '_' . Yii::$app->security->generateRandomString(8) . '_' . $safe;
        $s3Key = 'uploads/blog/' . $fname;
        
        // Определяем MIME-тип
        $contentType = $file->type;
        
        // Загружаем в S3
        $s3Api = Yii::$app->s3Api;
        $fileContent = file_get_contents($file->tempName);
        $s3Result = $s3Api->putFile($s3Key, $fileContent, $contentType);
        
        if ($s3Result === false) {
            return ['error' => 'Ошибка загрузки в S3'];
        }
        
        // Возвращаем публичный URL из S3
        $publicUrl = $s3Api->getPublicUrl($s3Key);
        return ['location' => $publicUrl];
    }

    /**
     * Публикация поста в группу ВКонтакте
     * @param int $id ID поста
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionPublishToVk($id)
    {
        $model = $this->findModel($id);
        
        // Получаем ID группы ВК из настроек или из URL
        $vkGroupId = Yii::$app->settings->get('vk_group_id');
        if (empty($vkGroupId)) {
            // Пытаемся извлечь ID из URL группы
            $vkUrl = Yii::$app->params['vk'] ?? '';
            if (preg_match('/club(\d+)/', $vkUrl, $matches)) {
                $vkGroupId = $matches[1];
            } elseif (preg_match('/public(\d+)/', $vkUrl, $matches)) {
                $vkGroupId = $matches[1];
            } else {
                Yii::$app->session->addFlash('danger', 'ID группы ВКонтакте не найден. Установите настройку vk_group_id или проверьте параметр vk в конфигурации.');
                return $this->redirect(['view', 'id' => $id]);
            }
        }
        
        // Преобразуем в отрицательное число для owner_id (группы имеют отрицательный ID)
        $vkGroupId = abs($vkGroupId);
        if ($vkGroupId > 0) {
            $vkGroupId = -$vkGroupId;
        }
        
        try {
            $vkHelper = new VkApiHelper();
            $vkHelper->setAccessToken(Yii::$app->settings->get('vk_user_token'));
            
            // Обрабатываем статью через OpenAI перед публикацией
            $postUrl = Yii::$app->params['baseUrl'] . $model->getUrl();
            $message = null;
            
            try {
                /** @var OpenAiVkPost $openAiVkPost */
                $openAiVkPost = Yii::$app->openAiVkPost;
                $processedMessage = $openAiVkPost->processForVk(
                    $model->name,
                    $model->content ?? '',
                    $model->description ?? null
                );
                
                if (!empty($processedMessage)) {
                    // Используем обработанный текст от OpenAI
                    $message = $processedMessage;
                    
                    // Добавляем ссылку на пост, если её нет в тексте
                    if (strpos($message, $postUrl) === false) {
                        $message .= "\n\nЧитать полностью: " . $postUrl;
                    }
                }
            } catch (\Exception $e) {
                Yii::error("OpenAI VK Post processing error: " . $e->getMessage(), __METHOD__);
                // Продолжаем с обычной обработкой, если OpenAI не сработал
            }
            
            // Если OpenAI не обработал, используем стандартную обработку
            if (empty($message)) {
                // Конвертируем HTML контент в VK markdown
                $content = $model->content ?? '';
                // Удаляем HTML теги и конвертируем в простой текст
                $content = strip_tags($content);
                // Заменяем множественные переносы строк на одинарные
                $content = preg_replace('/\n{3,}/', "\n\n", $content);
                // Обрезаем слишком длинный контент
                if (mb_strlen($content) > 1000) {
                    $content = mb_substr($content, 0, 1000) . '...';
                }
                
                $message = $model->name . "\n\n";
                
                // Добавляем описание, если есть
                if (!empty($model->description)) {
                    $description = strip_tags($model->description);
                    $message .= $description . "\n\n";
                }
                
                // Добавляем часть контента, если есть
                if (!empty($content)) {
                    $message .= $content . "\n\n";
                }
                
                // Добавляем ссылку на пост в формате VK markdown
                $message .= "Читать полностью: " . $postUrl;
            }
            
            // Получаем изображения из HTML-контента статьи (теги <img>)
            // Берем первое фото из blog_image
            $photoUrl = null;
            $firstBlogImage = $model->getBlogImages()->one();
            if ($firstBlogImage && !empty($firstBlogImage->link)) {
                $photoUrl = $firstBlogImage->getPublicUrl();
            }
            
            // Публикуем в группу с первым изображением (если есть)
            $result = $vkHelper->postToGroup($vkGroupId, $message, $photoUrl);
            
            if ($result !== false && !empty($result['response']['post_id'])) {
                Yii::$app->session->addFlash('success', 'Пост успешно опубликован в группу ВКонтакте!');
            } else {
                $error = 'Неизвестная ошибка';
                $errorCode = null;
                
                if (is_array($result) && isset($result['error'])) {
                    $error = $result['error']['error_msg'] ?? 'Неизвестная ошибка';
                    $errorCode = $result['error']['error_code'] ?? '';
                    if ($errorCode) {
                        $error = "[{$errorCode}] {$error}";
                    }
                    
                    // Если ошибка связана с правами доступа (код 15)
                    if ($errorCode == 15) {
                        $error .= '. Токен группы должен быть создан с правами (scopes): wall, photos. ';
                        $error .= 'Пересоздайте токен группы в настройках сообщества ВКонтакте с этими правами.';
                    }
                } elseif ($result === false) {
                    $error = 'Не удалось отправить запрос к VK API. Проверьте логи для деталей.';
                }
                
                Yii::$app->session->addFlash('danger', 'Ошибка при публикации в ВКонтакте: ' . $error);
            }
        } catch (\Exception $e) {
            Yii::$app->session->addFlash('danger', 'Ошибка при публикации в ВКонтакте: ' . $e->getMessage());
            Yii::error("VK publish error: " . $e->getMessage(), __METHOD__);
        }
        
        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * Публикация поста в Telegram канал
     * @param int $id ID поста
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionPublishToTelegram($id)
    {
        $model = $this->findModel($id);
        
        // Получаем токен и ID канала из настроек
        $telegramToken = Yii::$app->settings->get('telegramChannel_token');
        $telegramChannelId = Yii::$app->settings->get('telegramChannel_channelId');
        
        if (empty($telegramToken) || empty($telegramChannelId)) {
            Yii::$app->session->addFlash('danger', 'Токен или ID Telegram канала не настроены. Установите настройки telegramChannel_token и telegramChannel_channelId.');
            return $this->redirect(['view', 'id' => $id]);
        }
        
        try {
            $telegramHelper = new TelegramChannelHelper();
            $telegramHelper->setAccessToken($telegramToken);
            
            // Обрабатываем статью через OpenAI перед публикацией
            $postUrl = Yii::$app->params['baseUrl'] . $model->getUrl();
            $message = null;
            
            try {
                /** @var OpenAiTelegramPost $openAiTelegramPost */
                $openAiTelegramPost = Yii::$app->openAiTelegramPost;
                $processedMessage = $openAiTelegramPost->processForTelegram(
                    $model->name,
                    $model->content ?? '',
                    $model->description ?? null
                );
                
                if (!empty($processedMessage)) {
                    // Используем обработанный текст от OpenAI
                    $message = $processedMessage;
                    
                    // Добавляем ссылку на пост, если её нет в тексте
                    if (strpos($message, $postUrl) === false) {
                        $message .= "\n\nЧитать полностью: <a href=\"" . Html::encode($postUrl) . "\">" . Html::encode($postUrl) . "</a>";
                    }
                }
            } catch (\Exception $e) {
                Yii::error("OpenAI Telegram Post processing error: " . $e->getMessage(), __METHOD__);
                // Продолжаем с обычной обработкой, если OpenAI не сработал
            }
            
            // Если OpenAI не обработал, используем стандартную обработку
            if (empty($message)) {
                // Конвертируем HTML контент, оставляя разрешенные теги для Telegram
                $content = $model->content ?? '';
                // Удаляем все теги кроме разрешенных для Telegram: <b>, <i>, <code>, <a>
                $allowedTags = '<b><i><code><a>';
                $content = strip_tags($content, $allowedTags);
                // Заменяем множественные переносы строк на одинарные
                $content = preg_replace('/\n{3,}/', "\n\n", $content);
                
                $message = "<b>" . Html::encode($model->name) . "</b>\n\n";
                
                // Добавляем описание, если есть
                if (!empty($model->description)) {
                    $description = strip_tags($model->description, $allowedTags);
                    $message .= $description . "\n\n";
                }
                
                // Добавляем часть контента, если есть
                if (!empty($content)) {
                    $message .= $content . "\n\n";
                }
                
                // Добавляем ссылку на пост
                $message .= "Читать полностью: <a href=\"" . Html::encode($postUrl) . "\">Перейти к статье</a>";
            }
            
            // Получаем изображения из HTML-контента статьи (теги <img>)
            $photoUrls = [];
            $content = $model->content ?? '';
            
            if (!empty($content)) {
                // Извлекаем все URL изображений из тегов <img>
                preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
                
                if (!empty($matches[1])) {
                    foreach ($matches[1] as $imgSrc) {
                        // Формируем полный URL изображения
                        if (strpos($imgSrc, 'http') === 0) {
                            // Уже полный URL
                            $photoUrls[] = $imgSrc;
                        } elseif (strpos($imgSrc, '//') === 0) {
                            // URL без протокола
                            $photoUrls[] = 'https:' . $imgSrc;
                        } elseif (strpos($imgSrc, '/') === 0) {
                            // Относительный URL от корня
                            $photoUrls[] = Yii::$app->params['baseUrl'] . $imgSrc;
                        } else {
                            // Относительный URL
                            $photoUrls[] = Yii::$app->params['baseUrl'] . '/' . $imgSrc;
                        }
                    }
                }
            }
            
            // Если изображений в HTML нет, берем из blogImages
            if (empty($photoUrls)) {
                $blogImages = $model->getBlogImages()->all();
                foreach ($blogImages as $blogImage) {
                    // Используем метод getPublicUrl() для получения полного URL
                    if (!empty($blogImage->link)) {
                        $imageUrl = $blogImage->getPublicUrl();
                        $photoUrls[] = $imageUrl;
                    }
                }
            }

            // Публикуем в канал со всеми изображениями
            $result = $telegramHelper->postToChannel($telegramChannelId, $message, $photoUrls);
            
            if ($result !== false && !empty($result['ok']) && $result['ok'] === true) {
                Yii::$app->session->addFlash('success', 'Пост успешно опубликован в Telegram канал!');
            } else {
                $error = 'Неизвестная ошибка';
                if (is_array($result) && isset($result['description'])) {
                    $error = $result['description'];
                } elseif ($result === false) {
                    $error = 'Не удалось отправить запрос к Telegram API. Проверьте логи для деталей.';
                }
                Yii::$app->session->addFlash('danger', 'Ошибка при публикации в Telegram: ' . $error);
            }
        } catch (\Exception $e) {
            Yii::$app->session->addFlash('danger', 'Ошибка при публикации в Telegram: ' . $e->getMessage());
            Yii::error("Telegram publish error: " . $e->getMessage(), __METHOD__);
        }
        
        return $this->redirect(['view', 'id' => $id]);
    }


}
