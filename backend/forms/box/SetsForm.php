<?php

namespace backend\forms\box;

use common\models\box\Sets;
use common\models\box\SetsImage;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

class SetsForm extends Sets
{
    public $preview_file;

    public function rules(): array
    {
        return ArrayHelper::merge([
            [['preview_file'], 'trim'],
            [['preview_file'], 'file', 'skipOnEmpty' => true, 'extensions' => 'png'],
        ], parent::rules());
    }

    public function attributeLabels(): array
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'preview_file' => 'Изображение кейса',
        ]);
    }

    public function afterFind()
    {
        parent::afterFind();
        if (!empty($this->imageOrig)) {
            $this->preview_file = $this->imageOrig->getImagePubUrl();
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

        if (!$this->save()) {
            throw new \Exception('Sets not saved');
        }

        if (empty($this->id)) {
            $this->id = Yii::$app->db->getLastInsertID();
        }
        $this->preview_file = $this->_loadImage(UploadedFile::getInstance($this, 'preview_file'), $this->id);

        return true;
    }

    private function _loadImage($image, $setsId) {
        if (empty($image) || empty($image->tempName)) {
            return null;
        }
        $uploadDir = Yii::getAlias('@app/web/uploads');
        $fileUrl = "/sets/" . $this->id . "_" . md5(time()) . ".png";
        $filePath = $uploadDir . $fileUrl;
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, file_get_contents($image->tempName));
        SetsImage::createRecord($fileUrl, SetsImage::TYPE_ORIG, $setsId);
        return $filePath;
    }

}
