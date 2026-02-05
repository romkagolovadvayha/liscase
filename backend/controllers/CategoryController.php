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
            // Генерируем уникальное имя файла
            $safeName = preg_replace('~[^a-z0-9\.\-_]~i', '_', $file->name);
            $fileName = date('Ymd_His') . '_' . Yii::$app->security->generateRandomString(8) . '_' . $safeName;
            
            // Определяем расширение файла
            $extension = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
            if (empty($extension)) {
                // Если расширение не определено, определяем по MIME типу
                $mimeToExt = [
                    'image/png' => 'png',
                    'image/jpeg' => 'jpg',
                    'image/jpg' => 'jpg',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                ];
                $extension = $mimeToExt[$file->type] ?? 'png';
            }
            $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.' . $extension;
            
            // Путь в S3: uploads/categories/...
            $s3Key = 'uploads/categories/' . $fileName;
            
            // Читаем содержимое файла
            $fileContent = file_get_contents($file->tempName);
            if ($fileContent === false) {
                Yii::$app->session->setFlash('error', 'Ошибка чтения файла');
                return null;
            }
            
            // Определяем MIME-тип
            $contentType = $file->type;
            
            // Загружаем в S3
            $s3Api = Yii::$app->s3Api;
            $s3Result = $s3Api->putFile($s3Key, $fileContent, $contentType);
            
            if ($s3Result === false) {
                Yii::$app->session->setFlash('error', 'Ошибка загрузки изображения в S3');
                return null;
            }
            
            // Возвращаем путь в формате /images/categories/... для сохранения в БД
            return '/images/categories/' . $fileName;
            
        } catch (\Exception $e) {
            Yii::error('Error uploading category image: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'Ошибка при загрузке изображения: ' . $e->getMessage());
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

    protected function findModel($id)
    {
        if (($model = Category::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
