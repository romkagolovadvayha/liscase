<?php

namespace frontend\forms\blog;

use common\models\blog\BlogImage;
use common\models\building\Building;
use common\models\building\BuildingImage;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Yii;
use yii\helpers\ArrayHelper;
use yii\imagine\Image;
use yii\web\UploadedFile;

class BlogImageForm extends BlogImage
{
    public $image;

    public function rules(): array
    {
        return ArrayHelper::merge([
            [['image'], 'required'],
            [['image'], 'safe'],
            [['image'], 'file', 'skipOnEmpty' => true, 'extensions' => ['svg', 'jpg', 'jpeg', 'gif', 'png'], 'checkExtensionByMimeType' => false],
        ], parent::rules());
    }

    public function attributeLabels(): array
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'image' => Yii::t('common', 'Фотография'),
        ]);
    }

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        $this->created_at = date('Y-m-d H:i:s');

        if (!$this->validate()) {
            return false;
        }

        $file = UploadedFile::getInstances($this, 'image')[0];
        $exp = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));
        $baseName = $this->blog_id . '_' . md5($file->name);
        $fileName = $baseName . '.' . $exp;

        $s3Api = Yii::$app->s3Api;
        $tempDir = sys_get_temp_dir();
        $tempOriginal = $tempDir . '/' . uniqid('blog_orig_') . '.' . $exp;
        $isSvg = ($exp === 'svg');
        $temp100 = null;
        $temp400 = null;

        try {
            // Сохраняем загруженный файл во временный (без Tinify — ресайз из оригинала, как в DropForm)
            $fileContent = file_get_contents($file->tempName);
            if ($fileContent === false) {
                throw new \Exception(Yii::t('common', 'Ошибка чтения загруженного файла'));
            }
            file_put_contents($tempOriginal, $fileContent);

            $this->link = $fileName;
            $this->image_100 = null;
            $this->image_400 = null;

            // Уменьшенные копии 100 и 400 (только для растровых форматов), как в DropForm и DropImage
            if (!$isSvg) {
                $temp100 = $tempDir . '/' . uniqid('blog_100_') . '.png';
                $temp400 = $tempDir . '/' . uniqid('blog_400_') . '.png';

                if ($this->resizeImageToFile($tempOriginal, $temp100, 100)) {
                    $this->optimizeImageWithTinify($temp100);
                    $name100 = $baseName . '_100.png';
                    $content100 = file_get_contents($temp100);
                    if ($content100 !== false && $s3Api->putFile('blog/' . $name100, $content100, 'image/png') !== false) {
                        $this->image_100 = $name100;
                    } else {
                        Yii::error('Blog image: failed to upload image_100 to S3', __METHOD__);
                    }
                    @unlink($temp100);
                } else {
                    Yii::error('Blog image: resize to 100 failed for ' . $tempOriginal, __METHOD__);
                }

                if ($this->resizeImageToFile($tempOriginal, $temp400, 400)) {
                    $this->optimizeImageWithTinify($temp400);
                    $name400 = $baseName . '_400.png';
                    $content400 = file_get_contents($temp400);
                    if ($content400 !== false && $s3Api->putFile('blog/' . $name400, $content400, 'image/png') !== false) {
                        $this->image_400 = $name400;
                    } else {
                        Yii::error('Blog image: failed to upload image_400 to S3', __METHOD__);
                    }
                    @unlink($temp400);
                } else {
                    Yii::error('Blog image: resize to 400 failed for ' . $tempOriginal, __METHOD__);
                }
            }

            // Оригинал: Tinify (кроме SVG), затем загрузка в S3
            if (!$isSvg) {
                $this->optimizeImageWithTinify($tempOriginal);
            }
            $contentType = 'image/' . ($exp === 'jpg' ? 'jpeg' : ($exp === 'svg' ? 'svg+xml' : $exp));
            $fileContent = file_get_contents($tempOriginal);
            if ($fileContent === false) {
                throw new \Exception(Yii::t('common', 'Ошибка чтения обработанного файла'));
            }
            $s3Result = $s3Api->putFile('blog/' . $fileName, $fileContent, $contentType);
            if ($s3Result === false) {
                throw new \Exception(Yii::t('common', 'Ошибка загрузки изображения в S3'));
            }

            @unlink($tempOriginal);
        } catch (\Exception $e) {
            if (!empty($tempOriginal) && file_exists($tempOriginal)) {
                @unlink($tempOriginal);
            }
            if (!empty($temp100) && file_exists($temp100)) {
                @unlink($temp100);
            }
            if (!empty($temp400) && file_exists($temp400)) {
                @unlink($temp400);
            }
            throw $e;
        }

        if (!$this->save()) {
            throw new \Exception(Yii::t('common', 'Произошла ошибка при сохрании, напишите пожалуйста администратору сайта'));
        }

        return true;
    }

    /**
     * Ресайз изображения по длинной стороне (логика как в DropImage::resizeImage).
     *
     * @param string $sourcePath Исходный файл (jpg, png, gif)
     * @param string $destinationPath Куда сохранить PNG
     * @param int $newSize Максимальная ширина/высота
     * @return bool
     */
    protected function resizeImageToFile($sourcePath, $destinationPath, $newSize)
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if ($extension === 'svg') {
            return false;
        }
        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            Yii::error('Blog resize: source not readable ' . $sourcePath, __METHOD__);
            return false;
        }
        try {
            $image = Image::getImagine()->open($sourcePath);
        } catch (\Exception $e) {
            Yii::error('Blog resize: open failed ' . $sourcePath . ' - ' . $e->getMessage(), __METHOD__);
            return false;
        }
        $size = $image->getSize();
        $originalWidth = $size->getWidth();
        $originalHeight = $size->getHeight();
        if ($originalWidth <= 0 || $originalHeight <= 0) {
            Yii::error('Blog resize: invalid dimensions ' . $originalWidth . 'x' . $originalHeight, __METHOD__);
            return false;
        }
        $ratio = min($newSize / $originalWidth, $newSize / $originalHeight, 1);
        $newWidth = (int)($originalWidth * $ratio);
        $newHeight = (int)($originalHeight * $ratio);
        if ($newWidth <= 0 || $newHeight <= 0) {
            return false;
        }
        try {
            $resizedImage = $image->resize(new Box($newWidth, $newHeight));
            $resizedImage->save($destinationPath, [
                'format' => 'png',
                'png_compression_level' => 9,
                'flatten' => false,
            ]);
        } catch (\Exception $e) {
            Yii::error('Blog resize: save failed ' . $destinationPath . ' - ' . $e->getMessage(), __METHOD__);
            return false;
        }
        if (!file_exists($destinationPath) || filesize($destinationPath) === 0) {
            Yii::error('Blog resize: file not created or empty ' . $destinationPath, __METHOD__);
            @unlink($destinationPath);
            return false;
        }
        return true;
    }

    /**
     * Оптимизация изображения через TinyPNG (как в CategoryController, BuildingsController).
     *
     * @param string $filePath Путь к файлу изображения
     * @return bool
     */
    protected function optimizeImageWithTinify($filePath)
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }
        try {
            $keys = [
                'dY4rkCVRZxqxWD3wZcCdysWBbM7CGWB8',
                'SQMyJN0ZNs1zQfzrwBjMcsRHCnpffCbl',
                '8DTWnyW4m99062qs1X7p6dGgFcjM3Gb7',
            ];
            foreach ($keys as $key) {
                try {
                    \Tinify\setKey($key);
                    $source = \Tinify\fromFile($filePath);
                    $source->toFile($filePath);
                    return true;
                } catch (\Tinify\Exception $e) {
                    continue;
                }
            }
            Yii::info('Tinify compression skipped for blog image', __METHOD__);
            return false;
        } catch (\Exception $e) {
            Yii::info('Tinify compression error: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    private function _loadImages($images, $id) {
        if (empty($images)) {
            return null;
        }
        foreach ($images as $i => $image) {
            if (empty($image->tempName)) {
                continue;
            }
            $uploadDir = Yii::getAlias('@app/web/uploads');
            $fileName = "" . $this->id . "_" . md5(time() . $i) . ".png";
            $filePath = $uploadDir . "/buildings/" . $fileName;
            $filePathPreview = $uploadDir . "/buildings/preview_" . $fileName;
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath));
                chmod(dirname($filePath), 0777);
            }
            file_put_contents($filePath, file_get_contents($image->tempName));
            $model = new BuildingImage();
            $model->building_id = $id;
            $model->image = $fileName;
            $model->created_at = date(('Y-m-d H:i:s'));
            $model->save();

            $imagine = Image::getImagine();
            $image = $imagine->open($filePath);
            $diffWidth = 1;
            $diffHeight = 1;
            $offestX = 0;
            $offestY = 0;
            $newWidth = 200;
            $newHeight = 200;
            if ($image->getSize()->getWidth() > $image->getSize()->getHeight()) {
                $diffWidth = $image->getSize()->getWidth() / $image->getSize()->getHeight();
                $newWidth = 200 * $diffWidth;
                $offestX = $newWidth / 2 - 100;
            } else {
                $diffHeight = $image->getSize()->getHeight() / $image->getSize()->getWidth();
                $newHeight = 200 * $diffHeight;
                $offestY = $newHeight / 2 - 100;
            }

            if ($offestY < 0) {
                $offestY = 0;
            }
            if ($offestX < 0) {
                $offestX = 0;
            }

            $image
                ->resize(new Box($newWidth, $newHeight))
                ->crop(new Point($offestX, $offestY), new Box(200, 200))
                ->save($filePathPreview, ['quality' => 70]);

            if ($i >= 3) {
                break;
            }
        }
    }
}
