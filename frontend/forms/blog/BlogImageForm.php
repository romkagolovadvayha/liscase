<?php

namespace frontend\forms\blog;

use common\models\blog\BlogImage;
use common\models\box\BoxImage;
use common\models\building\BuildingResident;
use common\models\servers\Servers;
use Imagine\Image\Box;
use common\models\building\Building;
use common\models\building\BuildingImage;
use Imagine\Image\Point;
use React\Socket\Server;
use Yii;
use yii\base\BaseObject;
use yii\imagine\Image;
use yii\helpers\ArrayHelper;
use yii\validators\ImageValidator;
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
        $exp = explode('.', $file->name);
        $exp = $exp[count($exp) - 1];
        $fileName = $this->blog_id . "_" . md5($file->name) . "." . $exp;
        
        // Определяем MIME-тип
        $contentType = 'image/' . ($exp === 'jpg' ? 'jpeg' : ($exp === 'svg' ? 'svg+xml' : $exp));
        
        // Загружаем в S3
        $s3Api = Yii::$app->s3Api;
        $s3Key = 'uploads/blog/' . $fileName;
        $fileContent = file_get_contents($file->tempName);
        $s3Result = $s3Api->putFile($s3Key, $fileContent, $contentType);
        
        if ($s3Result === false) {
            throw new \Exception(Yii::t('common', 'Ошибка загрузки изображения в S3'));
        }

        $this->link = $fileName;
        if (!$this->save()) {
            throw new \Exception(Yii::t('common', 'Произошла ошибка при сохрании, напишите пожалуйста администратору сайта'));
        }

        return true;
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
