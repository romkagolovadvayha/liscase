<?php

namespace backend\forms\box;

use common\models\box\Select;
use common\models\box\SelectDrop;
use common\models\box\SelectImage;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

class SelectForm extends Select
{
    public $drop;
    public $preview_file;
    public $preview_file_open;

    public function rules(): array
    {
        return ArrayHelper::merge([
            [['drop', 'preview_file'], 'trim'],
            [['preview_file', 'preview_file_open'], 'file', 'skipOnEmpty' => true, 'extensions' => ['svg', 'jpg', 'png']],
        ], parent::rules());
    }

    public function attributeLabels(): array
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'drop' => 'Содержимое кейса',
            'preview_file' => 'Изображение кейса',
            'preview_file_open' => 'Доп. изображение (Не обязательно)',
        ]);
    }

    public function afterFind()
    {
        parent::afterFind();
        $this->drop = [];
        foreach ($this->selectDrop as $item) {
            $this->drop[] = $item->drop_id;
        }
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

        if (!$this->save()) {
            throw new \Exception('Select not saved');
        }

        $this->_removeDrop($this->drop);
        $this->_addDrop($this->drop);
        if (empty($this->id)) {
            $this->id = Yii::$app->db->getLastInsertID();
        }
        $this->preview_file = $this->_loadImage(UploadedFile::getInstance($this, 'preview_file'), $this->id, SelectImage::TYPE_ORIG);
        $this->preview_file_open = $this->_loadImage(UploadedFile::getInstance($this, 'preview_file_open'), $this->id, SelectImage::TYPE_ORIG_2);

        return true;
    }

    private function _loadImage($image, $selectId, $type) {
        if (empty($image) || empty($image->tempName)) {
            return null;
        }
        $uploadDir = Yii::getAlias('@app/web/uploads');
        $fileUrl = "/select/" . $this->id . "_" . $type . "_" . md5(time()) . ".png";
        $filePath = $uploadDir . $fileUrl;
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, file_get_contents($image->tempName));
        SelectImage::createRecord($fileUrl, $type, $selectId);
        return $filePath;
    }

    private function _removeDrop($dropIds) {
        foreach ($this->selectDrop as $item) {
            if (!in_array($item->drop_id, $dropIds)) {
                $item->delete();
            }
        }
    }

    private function _addDrop($dropIds) {
        if (empty($dropIds)) {
            return;
        }
        foreach ($dropIds as $dropId) {
            $insert = true;
            foreach ($this->selectDrop as $item) {
                if ($dropId === $item->drop_id) {
                    $insert = false;
                    break;
                }
            }
            if ($insert) {
                SelectDrop::createRecord($this->id, $dropId);
            }
        }
    }

}
