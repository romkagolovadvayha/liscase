<?php

namespace backend\controllers;

use backend\components\CrudController;
use backend\models\ServersRadioStationSearch;
use common\components\helpers\Role;
use common\models\servers\ServersRadioStation;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;
use yii\imagine\Image;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Yii;

/**
 * ServersRadioStationController implements the CRUD actions for ServersRadioStation model.
 */
class ServersRadioStationController extends CrudController
{
    /**
     * @return string
     */
    protected function _getSearchClassName()
    {
        return ServersRadioStationSearch::class;
    }

    protected function getIndexHeaderActions()
    {
        return [
            [
                'label' => '<i class="fas fa-plus"></i> Создать радиостанцию',
                'url' => ['create'],
                'class' => 'bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs font-medium transition-colors no-underline inline-flex items-center gap-1.5',
            ],
        ];
    }

    /**
     * @return string
     */
    protected function _getFormClassName()
    {
        return ServersRadioStation::class;
    }

    /**
     * @return string
     */
    protected function _getFormLayout()
    {
        return '@backend/views/layouts/main';
    }

    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            [
                'access' => [
                    'class' => \yii\filters\AccessControl::class,
                    'rules' => [
                        [
                            'allow' => true,
                            'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                        ],
                    ],
                ],
                'verbs' => [
                    'class' => VerbFilter::className(),
                    'actions' => [
                        'delete' => ['POST'],
                    ],
                ],
            ]
        );
    }

    /**
     * Переопределяем сохранение для обработки загрузки логотипа
     */
    public function actionCreate()
    {
        $this->layout = $this->_getFormLayout();
        $model = new ServersRadioStation();

        if ($this->request->isPost && $model->load($this->request->post())) {
            // Обработка загрузки логотипа
            $logoFile = UploadedFile::getInstance($model, 'logoFile');
            
            if ($logoFile) {
                // Обрабатываем и обрезаем изображение до 150x150
                $processedPath = $this->processLogoImage($logoFile->tempName, $logoFile->extension);
                
                if ($processedPath) {
                    // Генерируем уникальное имя файла
                    $fileName = uniqid('radio_logo_', true) . '_' . time() . '.png';
                    $s3Key = 'uploads/radio-stations/' . $fileName;
                    
                    // Загружаем обработанный файл в S3
                    $s3Api = Yii::$app->s3Api;
                    $uploaded = $s3Api->putFile($s3Key, $processedPath, 'image/png');
                    
                    // Удаляем временный файл
                    if (file_exists($processedPath) && $processedPath !== $logoFile->tempName) {
                        @unlink($processedPath);
                    }
                    
                    if ($uploaded) {
                        $model->logo = $s3Key;
                    } else {
                        $model->addError('logoFile', 'Ошибка загрузки логотипа в S3');
                    }
                } else {
                    $model->addError('logoFile', 'Ошибка обработки изображения');
                }
            }

            if ($model->save()) {
                // Сбрасываем кэш списка радиостанций
                Yii::$app->cache->delete('api_radio_list');
                
                Yii::$app->session->setFlash('success', 'Радиостанция успешно создана!');
                return $this->redirect(['view', 'id' => $model->id]);
            }
        } else {
            $model->loadDefaultValues();
            $model->status = ServersRadioStation::STATUS_ACTIVE;
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Обновление с обработкой загрузки логотипа
     */
    public function actionUpdate($id)
    {
        $this->layout = $this->_getFormLayout();
        $model = $this->findModel($id);
        $oldLogo = $model->logo;

        if ($this->request->isPost && $model->load($this->request->post())) {
            // Обработка загрузки логотипа
            $logoFile = UploadedFile::getInstance($model, 'logoFile');
            
            if ($logoFile) {
                // Обрабатываем и обрезаем изображение до 150x150
                $processedPath = $this->processLogoImage($logoFile->tempName, $logoFile->extension);
                
                if ($processedPath) {
                    // Генерируем уникальное имя файла
                    $fileName = uniqid('radio_logo_', true) . '_' . time() . '.png';
                    $s3Key = 'uploads/radio-stations/' . $fileName;
                    
                    // Загружаем обработанный файл в S3
                    $s3Api = Yii::$app->s3Api;
                    $uploaded = $s3Api->putFile($s3Key, $processedPath, 'image/png');
                    
                    // Удаляем временный файл
                    if (file_exists($processedPath) && $processedPath !== $logoFile->tempName) {
                        @unlink($processedPath);
                    }
                    
                    if ($uploaded) {
                        // Удаляем старый логотип из S3, если он был
                        if ($oldLogo) {
                            $s3Api->deleteFile($oldLogo);
                        }
                        
                        $model->logo = $s3Key;
                    } else {
                        $model->addError('logoFile', 'Ошибка загрузки логотипа в S3');
                    }
                } else {
                    $model->addError('logoFile', 'Ошибка обработки изображения');
                }
            } else {
                // Если файл не загружен, сохраняем старое значение
                $model->logo = $oldLogo;
            }

            if ($model->save()) {
                // Сбрасываем кэш списка радиостанций
                Yii::$app->cache->delete('api_radio_list');
                
                Yii::$app->session->setFlash('success', 'Радиостанция успешно обновлена!');
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Displays a single ServersRadioStation model.
     * @param int $id ID
     * @return string
     * @throws \yii\web\NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Удаление с удалением логотипа из S3
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        
        // Удаляем логотип из S3
        if (!empty($model->logo)) {
            $s3Api = Yii::$app->s3Api;
            $s3Api->deleteFile($model->logo);
        }

        $model->delete();

        // Сбрасываем кэш списка радиостанций
        Yii::$app->cache->delete('api_radio_list');

        Yii::$app->session->setFlash('success', 'Радиостанция успешно удалена!');
        return $this->redirect(['index']);
    }

    /**
     * Finds the ServersRadioStation model based on its primary key value.
     * If the model cannot be found, a 404 HTTP exception will be thrown.
     * @param int $id ID
     * @return ServersRadioStation the loaded model
     * @throws \yii\web\NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ServersRadioStation::findOne(['id' => $id])) !== null) {
            return $model;
        }

        throw new \yii\web\NotFoundHttpException('The requested page does not exist.');
    }

    /**
     * Обработка логотипа: обрезка до 150x150px и оптимизация
     *
     * @param string $sourcePath Путь к исходному файлу
     * @param string $extension Расширение файла
     * @return string|false Путь к обработанному файлу или false в случае ошибки
     */
    protected function processLogoImage($sourcePath, $extension)
    {
        $extension = strtolower($extension);
        
        // Для SVG просто возвращаем оригинал (не обрабатываем)
        if ($extension === 'svg') {
            return $sourcePath;
        }

        try {
            // Создаем временный файл для обработанного изображения
            $tempProcessed = sys_get_temp_dir() . '/' . uniqid('radio_logo_processed_', true) . '.png';
            
            // Открытие оригинального изображения
            $image = Image::getImagine()->open($sourcePath);
            $size = $image->getSize();
            
            $targetSize = 150; // 150x150px
            
            // Вычисляем размеры для обрезки (центрированная обрезка)
            $width = $size->getWidth();
            $height = $size->getHeight();
            
            // Определяем размер для масштабирования (чтобы меньшая сторона стала 150px)
            $ratio = $targetSize / min($width, $height);
            $newWidth = (int)($width * $ratio);
            $newHeight = (int)($height * $ratio);
            
            // Масштабируем изображение
            $resizedImage = $image->resize(new Box($newWidth, $newHeight));
            
            // Вычисляем координаты для обрезки (центрированная обрезка)
            $offsetX = (int)(($newWidth - $targetSize) / 2);
            $offsetY = (int)(($newHeight - $targetSize) / 2);
            
            // Обрезаем до 150x150px
            $croppedImage = $resizedImage->crop(
                new Point($offsetX, $offsetY),
                new Box($targetSize, $targetSize)
            );
            
            // Сохраняем в PNG
            $croppedImage->save($tempProcessed, [
                'format' => 'png',
                'png_compression_level' => 9,
                'flatten' => false, // сохраняет прозрачность
            ]);
            
            // Оптимизируем через TinyPNG
            $this->optimizeImageWithTinify($tempProcessed);
            
            return $tempProcessed;
        } catch (\Exception $e) {
            Yii::error('Error processing radio station logo: ' . $e->getMessage(), __METHOD__);
            return false;
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
                "yq4GXtx6DlyJhqWmgH0f5JPYYw68JNZY",
                "vtKS1W5X6sFdtyxgkvMfB58NzCPYT31X",
                "WmKCQdqXYJFhYtC2H8LgJwsk83Lm8L3h",
                "Lzh9MLcXk3NVNw9cNDZLGl6jWGkdHySw",
                "DFtVM70njvNkKXNBTkbQBB2nRHXjh59s",
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
            Yii::info('Tinify compression skipped for radio station logo', __METHOD__);
            return false;
        } catch(\Exception $e) {
            // Любая другая ошибка - просто пропускаем сжатие
            Yii::info('Tinify compression error for radio station logo: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}

