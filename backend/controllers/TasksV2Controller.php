<?php

namespace backend\controllers;

use common\components\helpers\Role;
use common\models\box\Drop;
use common\models\tasks_v2\TaskV2;
use Yii;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use backend\components\BackendController;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;
use yii\imagine\Image;

/**
 * Контроллер для управления заданиями v2 в админке
 */
class TasksV2Controller extends BackendController
{

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => [Role::ROLE_ADMIN, Role::ROLE_MODERATOR],
                    ],
                ],
            ],
        ];
    }


    /**
     * Список заданий
     * @return string
     */
    public function actionIndex()
    {
        $query = TaskV2::find();
        
        // Фильтры
        $isActive = Yii::$app->request->get('is_active');
        if ($isActive !== null && $isActive !== '') {
            $query->andWhere(['is_active' => (int)$isActive]);
        }
        
        $type = Yii::$app->request->get('type');
        if ($type && in_array($type, [TaskV2::TYPE_ONE_TIME, TaskV2::TYPE_REPEATABLE, TaskV2::TYPE_DAILY_REWARD])) {
            $query->andWhere(['type' => $type]);
        }
        
        $search = Yii::$app->request->get('search');
        if ($search) {
            $query->andWhere(['like', 'title', $search]);
        }
        
        $query->orderBy(['sort' => SORT_ASC, 'created_at' => SORT_DESC]);
        
        $dataProvider = new \yii\data\ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Создание задания
     * @return string|\yii\web\Response
     */
    public function actionCreate()
    {
        $model = new TaskV2();
        $model->is_active = 1;
        $model->sort = 0;
        $model->button_text = 'Проверить';

        if ($model->load(Yii::$app->request->post())) {
            // Обработка загрузки изображения
            $imageFile = UploadedFile::getInstance($model, 'imageFile');
            if ($imageFile && !empty($imageFile->tempName)) {
                $exp = explode('.', $imageFile->name);
                $exp = $exp[count($exp) - 1];
                if (!in_array(strtolower($exp), ['svg', 'png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                    Yii::$app->session->setFlash('danger', 'Разрешено загружать только изображения в формате SVG, PNG, JPG, GIF, WEBP!');
                } else {
                    $uploadDir = Yii::getAlias('@frontend/web/uploads/tasks-v2');
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                        chmod($uploadDir, 0775);
                    }
                    $fileName = uniqid() . '_' . md5(time()) . '.png';
                    $fileUrl = 'uploads/tasks-v2/' . $fileName;
                    $filePath = $uploadDir . '/' . $fileName;
                    
                    if ($this->processTaskImage($imageFile->tempName, $filePath)) {
                        chmod($filePath, 0664);
                        $model->image_path = $fileUrl;
                    } else {
                        Yii::$app->session->setFlash('danger', 'Ошибка при сохранении изображения!');
                    }
                }
            }
            
            // Обработка check_params из POST (массив)
            $checkParams = Yii::$app->request->post('check_params', []);
            if (is_array($checkParams)) {
                // Для ежедневных наград преобразуем rewards в правильный формат
                if ($model->check_type === TaskV2::CHECK_TYPE_DAILY_REWARD && isset($checkParams['rewards']) && is_array($checkParams['rewards'])) {
                    $rewards = [];
                    foreach ($checkParams['rewards'] as $reward) {
                        if (isset($reward['reward_type'])) {
                            if ($reward['reward_type'] === 'currency') {
                                // Валюта
                                $rewards[] = [
                                    'drop_id' => 843, // ID для валюты
                                    'amount' => isset($reward['amount']) ? (int)$reward['amount'] : 1,
                                ];
                            } elseif ($reward['reward_type'] === 'item' && !empty($reward['drop_id'])) {
                                // Предмет
                                $rewards[] = [
                                    'drop_id' => (int)$reward['drop_id'],
                                    'amount' => isset($reward['amount']) ? (int)$reward['amount'] : 1,
                                ];
                            }
                        } elseif (isset($reward['drop_id'])) {
                            // Старый формат (для обратной совместимости)
                            $rewards[] = [
                                'drop_id' => (int)$reward['drop_id'],
                                'amount' => isset($reward['amount']) ? (int)$reward['amount'] : 1,
                            ];
                        }
                    }
                    $checkParams['rewards'] = $rewards;
                }
                $model->check_params = $checkParams;
            } else {
                $model->check_params = [];
            }
            
            // Обработка extra_buttons из POST (массив)
            $extraButtons = Yii::$app->request->post('extra_buttons', []);
            if (is_array($extraButtons)) {
                // Фильтруем пустые значения
                $extraButtons = array_filter($extraButtons, function($button) {
                    return !empty($button['label']) && !empty($button['url']);
                });
                $extraButtons = array_values($extraButtons); // Переиндексируем
                $model->extra_buttons = $extraButtons;
            } else {
                $model->extra_buttons = [];
            }
            
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Задание успешно создано!');
                return $this->redirect(['index']);
            }
        }

        return $this->render('_form', [
            'model' => $model,
        ]);
    }

    /**
     * Редактирование задания
     * @param int $id
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            // Обработка загрузки изображения
            $imageFile = UploadedFile::getInstance($model, 'imageFile');
            if ($imageFile && !empty($imageFile->tempName)) {
                // Удаляем старое изображение, если есть
                if ($model->image_path) {
                    $oldFilePath = Yii::getAlias('@frontend/web') . '/' . ltrim($model->image_path, '/');
                    if (file_exists($oldFilePath)) {
                        @unlink($oldFilePath);
                    }
                }
                
                $exp = explode('.', $imageFile->name);
                $exp = $exp[count($exp) - 1];
                if (!in_array(strtolower($exp), ['svg', 'png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                    Yii::$app->session->setFlash('danger', 'Разрешено загружать только изображения в формате SVG, PNG, JPG, GIF, WEBP!');
                } else {
                    $uploadDir = Yii::getAlias('@frontend/web/uploads/tasks-v2');
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                        chmod($uploadDir, 0775);
                    }
                    $fileName = uniqid() . '_' . md5(time()) . '.png';
                    $fileUrl = 'uploads/tasks-v2/' . $fileName;
                    $filePath = $uploadDir . '/' . $fileName;
                    
                    if ($this->processTaskImage($imageFile->tempName, $filePath)) {
                        chmod($filePath, 0664);
                        $model->image_path = $fileUrl;
                    } else {
                        Yii::$app->session->setFlash('danger', 'Ошибка при сохранении изображения!');
                    }
                }
            }
            
            // Обработка check_params из POST (массив)
            $checkParams = Yii::$app->request->post('check_params', []);
            if (is_array($checkParams)) {
                // Для ежедневных наград преобразуем rewards в правильный формат
                if ($model->check_type === TaskV2::CHECK_TYPE_DAILY_REWARD && isset($checkParams['rewards']) && is_array($checkParams['rewards'])) {
                    $rewards = [];
                    foreach ($checkParams['rewards'] as $reward) {
                        if (isset($reward['reward_type'])) {
                            if ($reward['reward_type'] === 'currency') {
                                // Валюта
                                $rewards[] = [
                                    'drop_id' => 843, // ID для валюты
                                    'amount' => isset($reward['amount']) ? (int)$reward['amount'] : 1,
                                ];
                            } elseif ($reward['reward_type'] === 'item' && !empty($reward['drop_id'])) {
                                // Предмет
                                $rewards[] = [
                                    'drop_id' => (int)$reward['drop_id'],
                                    'amount' => isset($reward['amount']) ? (int)$reward['amount'] : 1,
                                ];
                            }
                        } elseif (isset($reward['drop_id'])) {
                            // Старый формат (для обратной совместимости)
                            $rewards[] = [
                                'drop_id' => (int)$reward['drop_id'],
                                'amount' => isset($reward['amount']) ? (int)$reward['amount'] : 1,
                            ];
                        }
                    }
                    $checkParams['rewards'] = $rewards;
                }
                $model->check_params = $checkParams;
            } else {
                $model->check_params = [];
            }
            
            // Обработка extra_buttons из POST (массив)
            $extraButtons = Yii::$app->request->post('extra_buttons', []);
            if (is_array($extraButtons)) {
                // Фильтруем пустые значения
                $extraButtons = array_filter($extraButtons, function($button) {
                    return !empty($button['label']) && !empty($button['url']);
                });
                $extraButtons = array_values($extraButtons); // Переиндексируем
                $model->extra_buttons = $extraButtons;
            } else {
                $model->extra_buttons = [];
            }
            
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Задание успешно обновлено!');
                return $this->redirect(['index']);
            }
        }

        return $this->render('_form', [
            'model' => $model,
        ]);
    }

    /**
     * Удаление задания
     * @param int $id
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        
        // Удаляем изображение, если есть
        if ($model->image_path) {
            $filePath = Yii::getAlias('@frontend/web') . '/' . ltrim($model->image_path, '/');
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        
        $model->delete();
        Yii::$app->session->setFlash('success', 'Задание успешно удалено!');
        
        return $this->redirect(['index']);
    }

    /**
     * Переключение активности задания
     * @param int $id
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionToggleActive($id)
    {
        $model = $this->findModel($id);
        $model->is_active = $model->is_active ? 0 : 1;
        $model->save(false);
        
        Yii::$app->session->setFlash('success', 'Статус задания изменен!');
        return $this->redirect(['index']);
    }

    /**
     * Поиск модели задания
     * @param int $id
     * @return TaskV2
     * @throws NotFoundHttpException
     */
    protected function findModel($id)
    {
        $model = TaskV2::findOne($id);
        if (!$model) {
            throw new NotFoundHttpException('Задание не найдено.');
        }
        return $model;
    }

    /**
     * Обработка изображения задания: ресайз до 270x270 и оптимизация через TinyPNG
     * @param string $sourcePath Путь к исходному файлу
     * @param string $destinationPath Путь для сохранения обработанного изображения
     * @return bool
     */
    private function processTaskImage($sourcePath, $destinationPath)
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if ($extension === 'svg') {
            // Для SVG просто копируем файл
            if (!file_exists(dirname($destinationPath))) {
                mkdir(dirname($destinationPath), 0775, true);
                chmod(dirname($destinationPath), 0775);
            }
            return copy($sourcePath, $destinationPath);
        }

        try {
            // Открытие оригинального изображения
            $image = Image::getImagine()->open($sourcePath);
            $size = $image->getSize();

            $maxWidth = 270;
            $maxHeight = 270;

            // Расчет масштабного коэффициента
            $ratio = min($maxWidth / $size->getWidth(), $maxHeight / $size->getHeight(), 1);

            // Новые размеры
            $newWidth = (int)($size->getWidth() * $ratio);
            $newHeight = (int)($size->getHeight() * $ratio);
            $box = new \Imagine\Image\Box($newWidth, $newHeight);

            // Создание уменьшенного изображения с сохранением пропорций
            $resizedImage = $image->resize($box);

            if (!file_exists(dirname($destinationPath))) {
                mkdir(dirname($destinationPath), 0775, true);
                chmod(dirname($destinationPath), 0775);
            }

            // Сохранение в PNG с уровнем сжатия 9
            $resizedImage->save($destinationPath, [
                'format' => 'png',
                'png_compression_level' => 9,
                'flatten' => false, // сохраняет прозрачность
            ]);

            // Оптимизация через TinyPNG
            \Tinify\setKey("dY4rkCVRZxqxWD3wZcCdysWBbM7CGWB8");
            try {
                $source = \Tinify\fromFile($destinationPath);
                $source->toFile($destinationPath);
            } catch(\Tinify\Exception $e) {
                \Tinify\setKey("SQMyJN0ZNs1zQfzrwBjMcsRHCnpffCbl");
                try {
                    $source = \Tinify\fromFile($destinationPath);
                    $source->toFile($destinationPath);
                } catch(\Tinify\Exception $e) {
                    \Tinify\setKey("8DTWnyW4m99062qs1X7p6dGgFcjM3Gb7");
                    try {
                        $source = \Tinify\fromFile($destinationPath);
                        $source->toFile($destinationPath);
                    } catch(\Tinify\Exception $e) {
                        \Tinify\setKey("yq4GXtx6DlyJhqWmgH0f5JPYYw68JNZY");
                        try {
                            $source = \Tinify\fromFile($destinationPath);
                            $source->toFile($destinationPath);
                        } catch(\Tinify\Exception $e) {
                            \Tinify\setKey("vtKS1W5X6sFdtyxgkvMfB58NzCPYT31X");
                            try {
                                $source = \Tinify\fromFile($destinationPath);
                                $source->toFile($destinationPath);
                            } catch(\Tinify\Exception $e) {
                                \Tinify\setKey("WmKCQdqXYJFhYtC2H8LgJwsk83Lm8L3h");
                                try {
                                    $source = \Tinify\fromFile($destinationPath);
                                    $source->toFile($destinationPath);
                                } catch(\Tinify\Exception $e) {
                                    \Tinify\setKey("Lzh9MLcXk3NVNw9cNDZLGl6jWGkdHySw");
                                    try {
                                        $source = \Tinify\fromFile($destinationPath);
                                        $source->toFile($destinationPath);
                                    } catch(\Tinify\Exception $e) {
                                        \Tinify\setKey("DFtVM70njvNkKXNBTkbQBB2nRHXjh59s");
                                        try {
                                            $source = \Tinify\fromFile($destinationPath);
                                            $source->toFile($destinationPath);
                                        } catch(\Tinify\Exception $e) {
                                            Yii::error("TinyPNG compression error for task image: " . $e->getMessage());
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            Yii::error("Error processing task image: " . $e->getMessage());
            return false;
        }
    }
}

