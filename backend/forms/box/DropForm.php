<?php

namespace backend\forms\box;

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
            $this->price = 1;
        }

        if (!$this->save()) {
            throw new \Exception('Drop not saved');
        }

        if (empty($this->id)) {
            $this->id = Yii::$app->db->getLastInsertID();
        }
        $this->preview_file = $this->_loadImage(UploadedFile::getInstance($this, 'preview_file'), $this->id, DropImage::TYPE_ORIG);
        $this->preview_file_open = $this->_loadImage(UploadedFile::getInstance($this, 'preview_file_open'), $this->id, DropImage::TYPE_ORIG_2);

        Drop::updateCache();
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
        $uploadDir = Yii::getAlias('@app/web/uploads');
        $fileUrl = "/drop/" . $this->id . "_" . $type . "_" . md5(time()) . ".{$exp}";
        $filePath = $uploadDir . $fileUrl;
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, file_get_contents($image->tempName));
        DropImage::createRecord($fileUrl, $type, $boxId);
        return $filePath;
    }

}
