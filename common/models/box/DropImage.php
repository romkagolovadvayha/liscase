<?php

namespace common\models\box;

use common\components\base\ActiveRecord;
use common\models\news\NewsContent;
use Yii;
use yii\imagine\Image;

/**
 * @property int                 $id
 * @property int                 $drop_id
 * @property int                 $type
 * @property string              $image
 * @property string              $created_at
 */
class DropImage extends ActiveRecord
{
    const TYPE_ORIG = 1;
    const TYPE_ORIG_2 = 2;
    const TYPE_64 = 3;
    const TYPE_150 = 4;
    const TYPE_100 = 5;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return 'drop_image';
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id'                  => Yii::t('common', 'ID'),
            'drop_id'               => Yii::t('common', 'Дроп'),
            'type'               => Yii::t('common', 'Тип'),
            'image'               => Yii::t('common', 'Изображение'),
            'created_at'          => Yii::t('common', 'Дата создания'),
        ];
    }

    public function getImagePubUrl($cdn = true) {
        if ($cdn) {
            return Yii::$app->settings->get('site_cdnUrl') . "/uploads" . $this->image;
        }
        return "/uploads" . $this->image;
    }

    public function getImagePubUrlShop($cdn = true) {
        $imagePref = explode('/', $this->image);
        $image = "";
        foreach ($imagePref as $i => $pref) {
            if (empty($pref)) {
                continue;
            }
            if ($i == count($imagePref) - 1) {
                $image .= "/shop_{$pref}";
                break;
            }
            $image .= "/{$pref}";
        }
        if ($cdn) {
            return Yii::$app->settings->get('site_cdnUrl') . "/uploads" . $image;
        }
        return Yii::$app->params['baseUrl'] . "/uploads" . $image;
    }

    public function rules(): array
    {
        return [
            [['drop_id', 'type', 'image'], 'required'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * @throws \Exception
     */
    public static function createRecord($image, $type, $dropId): bool
    {
        $models = self::find()
            ->andWhere(['drop_id' => $dropId])
            ->andWhere(['type' => $type])
            ->all();
        if (!empty($models)) {
            foreach ($models as $model) {
                $model->delete();
            }
        }
        $model = new self();
        $model->image = $image;
        $model->type = $type;
        $model->drop_id = $dropId;
        $model->created_at = date('Y-m-d H:i:s');
        try {
            $model->save(false);
        } catch (\Exception $e) {
            \Yii::info("Drop Image file string not save " . print_r($e->getMessage(), 1), 'problem');
            return false;
        }
        return true;
    }

    public static function resizeImage($sourcePath, $destinationPath, $newSize)
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if ($extension === 'svg') {
            // Не обрабатывать SVG — пропускаем или логируем
            return false;
        }

        // Открытие оригинального изображения
        $image = Image::getImagine()->open($sourcePath);
        $size = $image->getSize();

        $maxWidth = $newSize;
        $maxHeight = $newSize;

        // Расчет масштабного коэффициента
        $ratio = min($maxWidth / $size->getWidth(), $maxHeight / $size->getHeight(), 1);

        // Новые размеры
        $newWidth = (int)($size->getWidth() * $ratio);
        $newHeight = (int)($size->getHeight() * $ratio);
        $box = new \Imagine\Image\Box($newWidth, $newHeight);

        // Создание уменьшенного изображения с сохранением пропорций
        $resizedImage = $image->resize($box);

        if (!file_exists(dirname($destinationPath))) {
            mkdir(dirname($destinationPath));
            chmod(dirname($destinationPath), 0777);
        }

        // Сохранение в PNG с уровнем сжатия 6 (примерно 70%)
        $resizedImage->save($destinationPath, [
            'format' => 'png',
            'png_compression_level' => 9, // 0 — без сжатия, 9 — максимум
            'flatten' => false, // сохраняет прозрачность
        ]);

        if ($newSize == 150 || $newSize == 64) {
            \Tinify\setKey("dY4rkCVRZxqxWD3wZcCdysWBbM7CGWB8"); // ← сюда свой ключ
            try {
                $source = \Tinify\fromFile($destinationPath);
                $source->toFile($destinationPath); // перезаписывает исходный файл
            } catch(\Tinify\Exception $e) {
                \Tinify\setKey("SQMyJN0ZNs1zQfzrwBjMcsRHCnpffCbl"); // ← сюда свой ключ
                try {
                    $source = \Tinify\fromFile($destinationPath);
                    $source->toFile($destinationPath); // перезаписывает исходный файл
                } catch(\Tinify\Exception $e) {
                    \Tinify\setKey("8DTWnyW4m99062qs1X7p6dGgFcjM3Gb7"); // ← сюда свой ключ
                    try {
                        $source = \Tinify\fromFile($destinationPath);
                        $source->toFile($destinationPath); // перезаписывает исходный файл
                    } catch(\Tinify\Exception $e) {
                        \Tinify\setKey("yq4GXtx6DlyJhqWmgH0f5JPYYw68JNZY"); // ← сюда свой ключ
                        try {
                            $source = \Tinify\fromFile($destinationPath);
                            $source->toFile($destinationPath); // перезаписывает исходный файл
                        } catch(\Tinify\Exception $e) {
                            \Tinify\setKey("vtKS1W5X6sFdtyxgkvMfB58NzCPYT31X"); // ← сюда свой ключ
                            try {
                                $source = \Tinify\fromFile($destinationPath);
                                $source->toFile($destinationPath); // перезаписывает исходный файл
                            } catch(\Tinify\Exception $e) {
                                \Tinify\setKey("WmKCQdqXYJFhYtC2H8LgJwsk83Lm8L3h"); // ← сюда свой ключ
                                try {
                                    $source = \Tinify\fromFile($destinationPath);
                                    $source->toFile($destinationPath); // перезаписывает исходный файл
                                } catch(\Tinify\Exception $e) {
                                    \Tinify\setKey("Lzh9MLcXk3NVNw9cNDZLGl6jWGkdHySw"); // ← сюда свой ключ
                                    try {
                                        $source = \Tinify\fromFile($destinationPath);
                                        $source->toFile($destinationPath); // перезаписывает исходный файл
                                    } catch(\Tinify\Exception $e) {
                                        \Tinify\setKey("DFtVM70njvNkKXNBTkbQBB2nRHXjh59s"); // ← сюда свой ключ
                                        try {
                                            $source = \Tinify\fromFile($destinationPath);
                                            $source->toFile($destinationPath); // перезаписывает исходный файл
                                        } catch(\Tinify\Exception $e) {
                                            Yii::error("TinyPNG compression error: " . $e->getMessage());
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return true;
    }
}
