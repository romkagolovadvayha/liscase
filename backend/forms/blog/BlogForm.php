<?php

namespace backend\forms\blog;

use common\models\blog\Blog;
use common\models\blog\BlogImage;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

class BlogForm extends Blog
{
    public $preview_file;

    public function rules(): array
    {
        return ArrayHelper::merge([
                                      [['preview_file'], 'trim'],
                                      [['preview_file'], 'file', 'skipOnEmpty' => true, 'extensions' => 'png,jpg'],
                                  ], parent::rules());
    }

    public function attributeLabels(): array
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'preview_file' => 'Изображение',
        ]);
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
            throw new \Exception('Box not saved');
        }

        if (empty($this->id)) {
            $this->id = Yii::$app->db->getLastInsertID();
        }
        $this->preview_file = $this->_loadImage(UploadedFile::getInstance($this, 'preview_file'), $this->id);

        return true;
    }

    private function _loadImage($image, $boxId) {
        if (empty($image) || empty($image->tempName)) {
            return null;
        }
        $uploadDir = Yii::getAlias('@app/web/uploads');
        $fileUrl = "/blog/" . $this->id . "_" . md5(time()) . ".png";
        $filePath = $uploadDir . $fileUrl;
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath));
            chmod(dirname($filePath), 0777);
        }
        file_put_contents($filePath, file_get_contents($image->tempName));
        $image = new BlogImage();
        $image->link = $fileUrl;
        $image->blog_id = $boxId;
        $image->description = $this->name;
        $image->save();
        return $filePath;
    }

}
