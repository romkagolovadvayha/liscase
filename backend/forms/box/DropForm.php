<?php

namespace backend\forms\box;

use common\components\queue\process\DropUpdateCacheJob;
use common\models\box\Box;
use common\models\box\BoxDrop;
use common\models\box\BoxImage;
use common\models\box\Drop;
use common\models\box\DropImage;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

class DropForm extends Drop
{

    public $preview_file;
    public $preview_file_open;
    public $isSubmit;

    public function rules(): array
    {
        return ArrayHelper::merge([
                                      [['name', 'eng_name', 'isSubmit', 'market_status', 'min_box', 'max_box', 'description', 'rust_id', 'type_id', 'price', 'count', 'discount', 'preview_file', 'command', 'blocked_hour'], 'trim'],
                                      [['preview_file', 'preview_file_open'], 'file', 'skipOnEmpty' => true, 'extensions' => ['svg', 'jpg', 'png']],
                                  ], parent::rules());
    }

    public function attributeLabels(): array
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'preview_file' => 'Изображение предмета',
            'preview_file_open' => 'Доп. изображение (Не обязательно)',
        ]);
    }

    public function afterFind()
    {
        parent::afterFind();
        if (!empty($this->imageOrig)) {
            $this->preview_file = $this->imageOrig->getImagePubUrl();
        }
        if (!empty($this->imageOrig2)) {
            $this->preview_file_open = $this->imageOrig2->getImagePubUrl();
        }
    }

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        if ($this->isNewRecord) {
            $this->status = 1;
            $this->created_at = date('Y-m-d H:i:s');
        }
        if (!$this->validate()) {
            return false;
        }
        if (!$this->isSubmit) {
            return false;
        }
        if (empty($this->min_box)) {
            $this->min_box = 0;
        }
        if (empty($this->max_box)) {
            $this->max_box = 0;
        }
        if (empty($this->price)) {
            $this->price = 0;
        }

        if (!$this->save()) {
            throw new \Exception('Drop not saved');
        }

        if (empty($this->id)) {
            $this->id = Yii::$app->db->getLastInsertID();
        }
        $this->preview_file = $this->_loadImage(UploadedFile::getInstance($this, 'preview_file'), $this->id, DropImage::TYPE_ORIG);
        $this->preview_file_open = $this->_loadImage(UploadedFile::getInstance($this, 'preview_file_open'), $this->id, DropImage::TYPE_ORIG_2);

        \Yii::$app->queueProcess->push(new DropUpdateCacheJob());
        return true;
    }

    private function _loadImage($image, $boxId, $type) {
        if (empty($image) || empty($image->tempName)) {
            return null;
        }
        $exp = explode('.', $image->name);
        $exp = $exp[count($exp) - 1];
        if (!in_array($exp, ['svg', 'png', 'jpg', 'ico', 'webp'])) {
            Yii::$app->session->setFlash('danger', 'Разрешенно загружать только изображения в формате SVG, PNG, JPG, ICO, WEBP!');
            return null;
        }
        
        $filename = $this->id . "_" . $type . "_" . md5(time()) . ".{$exp}";
        $fileUrl = "/drop/{$filename}";
        
        // Определяем MIME-тип
        $contentType = 'image/' . ($exp === 'jpg' ? 'jpeg' : ($exp === 'svg' ? 'svg+xml' : $exp));
        
        // Загружаем оригинальное изображение в S3
        $s3Key = 'uploads' . $fileUrl;
        $fileContent = file_get_contents($image->tempName);
        $s3Result = Yii::$app->s3Api->putFile($s3Key, $fileContent, $contentType);
        
        if ($s3Result === false) {
            Yii::$app->session->setFlash('danger', 'Ошибка загрузки изображения в S3');
            return null;
        }
        
        // Создаем временный файл для ресайза
        $tempDir = sys_get_temp_dir();
        $tempFilePath = $tempDir . '/' . uniqid('drop_') . '.' . $exp;
        file_put_contents($tempFilePath, $fileContent);
        
        // Создаем ресайзы и загружаем их в S3
        if (file_exists($tempFilePath) && $exp !== 'svg') {
            $newPath150 = "/drop150/" . $filename;
            $s3Key150 = 'uploads' . $newPath150;
            $tempPath150 = $tempDir . '/' . uniqid('drop150_') . '.png';
            DropImage::resizeImage($tempFilePath, $tempPath150, 150);
            if (file_exists($tempPath150)) {
                Yii::$app->s3Api->putFile($s3Key150, file_get_contents($tempPath150), 'image/png');
                DropImage::createRecord($newPath150, DropImage::TYPE_150, $boxId);
                @unlink($tempPath150);
            }
            
            $newPath64 = "/drop64/" . $filename;
            $s3Key64 = 'uploads' . $newPath64;
            $tempPath64 = $tempDir . '/' . uniqid('drop64_') . '.png';
            DropImage::resizeImage($tempFilePath, $tempPath64, 64);
            if (file_exists($tempPath64)) {
                Yii::$app->s3Api->putFile($s3Key64, file_get_contents($tempPath64), 'image/png');
                DropImage::createRecord($newPath64, DropImage::TYPE_64, $boxId);
                @unlink($tempPath64);
            }
            
            $newPath100 = "/drop100/" . $filename;
            $s3Key100 = 'uploads' . $newPath100;
            $tempPath100 = $tempDir . '/' . uniqid('drop100_') . '.png';
            DropImage::resizeImage($tempFilePath, $tempPath100, 100);
            if (file_exists($tempPath100)) {
                Yii::$app->s3Api->putFile($s3Key100, file_get_contents($tempPath100), 'image/png');
                DropImage::createRecord($newPath100, DropImage::TYPE_100, $boxId);
                @unlink($tempPath100);
            }
        }
        
        // Удаляем временный файл
        @unlink($tempFilePath);
        
        DropImage::createRecord($fileUrl, $type, $boxId);
        return $s3Key;
    }

}
