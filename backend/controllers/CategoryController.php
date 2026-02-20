<?php

namespace backend\controllers;

use Yii;
use common\components\helpers\Role;
use common\models\box\Category;
use common\models\box\CategorySearch;
use backend\components\BackendController;
use common\models\box\Drop;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;
use yii\imagine\Image;
use Imagine\Image\Box;
use Imagine\Image\Point;

/**
 * CategoryController implements the CRUD actions for Category model.
 */
class CategoryController extends BackendController
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
                        'roles' => [Role::ROLE_ADMIN],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ]);
    }

    /**
     * Lists all Category models.
     *
     * @return string
     */
    public function actionIndex()
    {
        $searchModel = new CategorySearch();
        $dataProvider = $searchModel->search($this->request->queryParams);

        $this->view->params['showFilters'] = true;
        $this->view->params['searchModel'] = $searchModel;
        $this->view->params['headerActions'] = [
            [
                'label' => '<i class="fas fa-plus"></i> Добавить',
                'url' => ['create'],
                'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Category model.
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
     * Creates a new Category model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new Category();

        if ($this->request->isPost) {
            if ($model->load($this->request->post())) {
                // Обрабатываем загрузку изображения
                $imageFile = UploadedFile::getInstance($model, 'image');
                if ($imageFile) {
                    $imagePath = $this->uploadImage($imageFile);
                    if ($imagePath) {
                        $model->image = $imagePath;
                    }
                }
                
                if ($model->save()) {
                    Drop::updateCache();
                    
                    // Сбрасываем кэш категорий товаров (все варианты)
                    Yii::$app->cache->delete('api_products_categories_all');
                    Yii::$app->cache->delete('api_products_categories_0');
                    Yii::$app->cache->delete('api_products_categories_1');
                    
                    return $this->redirect(['view', 'id' => $model->id]);
                }
            }
        } else {
            $model->loadDefaultValues();
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Category model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param int $id ID
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $oldImage = $model->image;

        if ($this->request->isPost && $model->load($this->request->post())) {
            // Обрабатываем загрузку изображения
            $imageFile = UploadedFile::getInstance($model, 'image');
            if ($imageFile) {
                // Удаляем старое изображение из S3, если оно есть
                if ($oldImage && strpos($oldImage, '/images/') === 0) {
                    $this->deleteImage($oldImage);
                }
                
                $imagePath = $this->uploadImage($imageFile);
                if ($imagePath) {
                    $model->image = $imagePath;
                }
            }
            
            if ($model->save()) {
                Drop::updateCache();
                
                // Сбрасываем кэш категорий товаров (все варианты)
                Yii::$app->cache->delete('api_products_categories_all');
                Yii::$app->cache->delete('api_products_categories_0');
                Yii::$app->cache->delete('api_products_categories_1');
                
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Category model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param int $id ID
     * @return \yii\web\Response
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        $imagePath = $model->image;
        
        // Удаляем изображение из S3 перед удалением категории
        if ($imagePath) {
            $this->deleteImage($imagePath);
        }
        
        $model->delete();

        // Сбрасываем кэш категорий товаров (все варианты)
        Yii::$app->cache->delete('api_products_categories_all');
        Yii::$app->cache->delete('api_products_categories_0');
        Yii::$app->cache->delete('api_products_categories_1');

        return $this->redirect(['index']);
    }

    /**
     * Finds the Category model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return Category the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    /**
     * Загрузка изображения категории в S3
     * 
     * @param UploadedFile $file
     * @return string|null Путь к изображению в формате /images/categories/...
     */
    protected function uploadImage($file)
    {
        if (!$file) {
            return null;
        }

        // Проверяем тип файла
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
        if (!in_array($file->type, $allowedTypes)) {
            Yii::$app->session->setFlash('error', 'Разрешены только изображения (PNG, JPEG, GIF, WebP)');
            return null;
        }

        // Проверяем размер файла (максимум 5 МБ)
        if ($file->size > 5 * 1024 * 1024) {
            Yii::$app->session->setFlash('error', 'Размер файла не должен превышать 5 МБ');
            return null;
        }

        try {
            // Создаем временную директорию для обработки
            $tempDir = sys_get_temp_dir();
            $tempOriginal = $tempDir . '/' . uniqid('category_orig_') . '.png';
            $tempResized = $tempDir . '/' . uniqid('category_100_') . '.png';
            
            // Сохраняем оригинал во временный файл
            if (!move_uploaded_file($file->tempName, $tempOriginal)) {
                Yii::$app->session->setFlash('error', 'Ошибка сохранения временного файла');
                return null;
            }
            
            // Оптимизируем через TinyPNG
            $this->optimizeImageWithTinify($tempOriginal);
            
            // Открываем изображение через Imagine
            $image = Image::getImagine()->open($tempOriginal);
            $size = $image->getSize();
            
            // Ресайз до 100x100px с сохранением пропорций и обрезкой по центру
            $targetSize = 100;
            $width = $size->getWidth();
            $height = $size->getHeight();
            
            // Вычисляем масштаб для покрытия всего квадрата 100x100
            $scale = max($targetSize / $width, $targetSize / $height);
            $newWidth = (int)($width * $scale);
            $newHeight = (int)($height * $scale);
            
            // Ресайзим изображение
            $resizedImage = $image->resize(new Box($newWidth, $newHeight));
            
            // Обрезаем по центру до 100x100
            $offsetX = max(0, (int)(($newWidth - $targetSize) / 2));
            $offsetY = max(0, (int)(($newHeight - $targetSize) / 2));
            $croppedImage = $resizedImage->crop(new Point($offsetX, $offsetY), new Box($targetSize, $targetSize));
            
            // Сохраняем обработанное изображение
            $croppedImage->save($tempResized, [
                'format' => 'png',
                'png_compression_level' => 9,
            ]);
            
            // Генерируем уникальное имя файла
            $fileName = date('Ymd_His') . '_' . Yii::$app->security->generateRandomString(8) . '.png';
            
            // Путь в S3: uploads/categories/...
            $s3Key = 'uploads/categories/' . $fileName;
            
            // Читаем содержимое обработанного файла
            $fileContent = file_get_contents($tempResized);
            if ($fileContent === false) {
                Yii::$app->session->setFlash('error', 'Ошибка чтения обработанного файла');
                @unlink($tempOriginal);
                @unlink($tempResized);
                return null;
            }
            
            // Загружаем в S3
            $s3Api = Yii::$app->s3Api;
            $s3Result = $s3Api->putFile($s3Key, $fileContent, 'image/png');
            
            // Удаляем временные файлы
            @unlink($tempOriginal);
            @unlink($tempResized);
            
            if ($s3Result === false) {
                Yii::$app->session->setFlash('error', 'Ошибка загрузки изображения в S3');
                return null;
            }
            
            // Возвращаем путь в формате /images/categories/... для сохранения в БД
            return '/images/categories/' . $fileName;
            
        } catch (\Exception $e) {
            Yii::error('Error uploading category image: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'Ошибка при загрузке изображения: ' . $e->getMessage());
            @unlink($tempOriginal ?? null);
            @unlink($tempResized ?? null);
            return null;
        }
    }

    /**
     * Удаление изображения категории из S3
     * 
     * @param string $imagePath Путь к изображению в формате /images/categories/...
     */
    protected function deleteImage($imagePath)
    {
        if (empty($imagePath)) {
            return;
        }

        try {
            // Преобразуем путь из /images/categories/... в uploads/categories/...
            if (strpos($imagePath, '/images/') === 0) {
                $s3Key = 'uploads' . $imagePath;
            } elseif (strpos($imagePath, '/uploads/') === 0) {
                $s3Key = ltrim($imagePath, '/');
            } else {
                return;
            }
            
            $s3Api = Yii::$app->s3Api;
            $s3Api->deleteFile($s3Key);
        } catch (\Exception $e) {
            Yii::error('Error deleting category image: ' . $e->getMessage(), __METHOD__);
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
                "8DTWnyW4m99062qs1X7p6dGgFcjM3Gb7",
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
            Yii::info('Tinify compression skipped for category image', __METHOD__);
            return false;
        } catch(\Exception $e) {
            // Любая другая ошибка - просто пропускаем сжатие
            Yii::info('Tinify compression error: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    protected function findModel($id)
    {
        if (($model = Category::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
