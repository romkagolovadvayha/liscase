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
        // Формируем ключ для S3
        $s3Key = 'uploads' . $this->image;
        
        // Используем S3Api для получения публичного URL (он использует настройки из settings)
        return Yii::$app->s3Api->getPublicUrl($s3Key);
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
        $s3Key = 'uploads' . $image;
        
        // Используем S3Api для получения публичного URL (он использует настройки из settings)
        return Yii::$app->s3Api->getPublicUrl($s3Key);
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

        // Проверяем, что исходный файл существует и доступен для чтения
        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            \Yii::error('Source image file does not exist or is not readable: ' . $sourcePath, __METHOD__);
            return false;
        }

        // Открытие оригинального изображения
        try {
            $image = Image::getImagine()->open($sourcePath);
        } catch (\Exception $e) {
            \Yii::error('Failed to open image file: ' . $sourcePath . ' - ' . $e->getMessage(), __METHOD__);
            return false;
        }
        
        $size = $image->getSize();
        $originalWidth = $size->getWidth();
        $originalHeight = $size->getHeight();
        
        // Проверяем, что изображение имеет валидные размеры
        if ($originalWidth <= 0 || $originalHeight <= 0) {
            \Yii::error('Invalid image dimensions: ' . $originalWidth . 'x' . $originalHeight . ' for file: ' . $sourcePath, __METHOD__);
            return false;
        }

        $maxWidth = $newSize;
        $maxHeight = $newSize;

        // Расчет масштабного коэффициента
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight, 1);

        // Новые размеры
        $newWidth = (int)($originalWidth * $ratio);
        $newHeight = (int)($originalHeight * $ratio);
        
        // Проверяем, что новые размеры валидны
        if ($newWidth <= 0 || $newHeight <= 0) {
            \Yii::error('Invalid calculated dimensions: ' . $newWidth . 'x' . $newHeight . ' for size: ' . $newSize, __METHOD__);
            return false;
        }
        
        $box = new \Imagine\Image\Box($newWidth, $newHeight);

        // Создание уменьшенного изображения с сохранением пропорций
        try {
            $resizedImage = $image->resize($box);
        } catch (\Exception $e) {
            \Yii::error('Failed to resize image: ' . $sourcePath . ' to ' . $newWidth . 'x' . $newHeight . ' - ' . $e->getMessage(), __METHOD__);
            return false;
        }

        // Создаем директорию для файла назначения, если она не существует
        $destinationDir = dirname($destinationPath);
        if ($destinationDir !== '.' && $destinationDir !== '/' && !file_exists($destinationDir)) {
            if (!@mkdir($destinationDir, 0777, true)) {
                \Yii::error('Failed to create destination directory: ' . $destinationDir, __METHOD__);
                return false;
            }
            @chmod($destinationDir, 0777);
        }

        // Сохранение в PNG с уровнем сжатия 6 (примерно 70%)
        try {
            $resizedImage->save($destinationPath, [
                'format' => 'png',
                'png_compression_level' => 9, // 0 — без сжатия, 9 — максимум
                'flatten' => false, // сохраняет прозрачность
            ]);
            
            // Небольшая задержка для гарантии записи на диск
            usleep(10000); // 10ms
            
            // Проверяем, что файл был успешно создан
            if (!file_exists($destinationPath)) {
                \Yii::error('Failed to save resized image to: ' . $destinationPath . ' - file does not exist after save', __METHOD__);
                return false;
            }
            if (!is_readable($destinationPath)) {
                \Yii::error('Failed to save resized image to: ' . $destinationPath . ' - file is not readable', __METHOD__);
                return false;
            }
            if (filesize($destinationPath) === 0) {
                \Yii::error('Failed to save resized image to: ' . $destinationPath . ' - file is empty', __METHOD__);
                @unlink($destinationPath);
                return false;
            }
        } catch (\Exception $e) {
            \Yii::error('Exception while saving resized image: ' . $destinationPath . ' - ' . $e->getMessage() . ' (' . get_class($e) . ')', __METHOD__);
            return false;
        } catch (\Throwable $e) {
            \Yii::error('Throwable while saving resized image: ' . $destinationPath . ' - ' . $e->getMessage() . ' (' . get_class($e) . ')', __METHOD__);
            return false;
        }

        if ($newSize == 150 || $newSize == 64 || $newSize == 200) {
            // Пытаемся сжать изображение через Tinify (не критично, если не получится)
            // Используем только первый ключ с коротким таймаутом для быстрой обработки
            $tinifySuccess = false;
            try {
                // Устанавливаем таймаут для Tinify (если поддерживается)
                if (method_exists('\Tinify\Tinify', 'setTimeout')) {
                    \Tinify\Tinify::setTimeout(3); // 3 секунды таймаут
                }
                \Tinify\setKey("dY4rkCVRZxqxWD3wZcCdysWBbM7CGWB8"); // ← сюда свой ключ
                $source = \Tinify\fromFile($destinationPath);
                $source->toFile($destinationPath); // перезаписывает исходный файл
                $tinifySuccess = true;
            } catch(\Tinify\Exception $e) {
                // Если первый ключ не сработал, пробуем еще один раз, но не все ключи
                try {
                    \Tinify\setKey("SQMyJN0ZNs1zQfzrwBjMcsRHCnpffCbl"); // ← сюда свой ключ
                    $source = \Tinify\fromFile($destinationPath);
                    $source->toFile($destinationPath);
                    $tinifySuccess = true;
                } catch(\Tinify\Exception $e2) {
                    // Пропускаем остальные ключи для ускорения - просто логируем
                    \Yii::info('Tinify compression skipped: ' . $e2->getMessage(), __METHOD__);
                }
            } catch(\Exception $e) {
                // Любая другая ошибка - просто пропускаем сжатие
                \Yii::info('Tinify compression error: ' . $e->getMessage(), __METHOD__);
            }
            
            // Проверяем, что файл все еще существует после попыток сжатия
            if (!file_exists($destinationPath) || !is_readable($destinationPath)) {
                \Yii::error('File was deleted or became unreadable after Tinify compression: ' . $destinationPath, __METHOD__);
                return false;
            }
            
            return true;
        }
        
        return true;
    }
}
