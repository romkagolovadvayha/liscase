<?php

namespace backend\forms\blog;

use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use common\models\blog\BlogImage;
use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;

class BlogForm extends Blog
{

    /**
     * @return bool
     */
    public function saveRecord(): bool
    {
        if ($this->isNewRecord) {
            $this->status = 1;
            $this->created_at = date('Y-m-d H:i:s');
        }
        if (empty($this->link_name)) {
            $this->link_name = BlogCategory::toLinkName($this->name);
        }
        if (!$this->validate()) {
            return false;
        }
        
        if (empty($this->keywords)) {
            $description = Yii::$app->openAi->getPostMeta($this->name, ' Сейчас содержание статьи такое: "' . $this->content . '". Ты должен переписать статью более удобно для читателя.', $this->blogCategory->name, $this->blogCategory->description);
            preg_match_all('#<keywords>(.+?)</keywords>#is', $description, $keywords);
            preg_match_all('#<descriptionShort>(.+?)</descriptionShort>#is', $description, $descriptionShort);

            $this->keywords = $keywords[1][0];
            $this->description = $descriptionShort[1][0];
        }

        if (!$this->save()) {
            throw new \Exception('Blog not saved');
        }

        if (empty($this->id)) {
            $this->id = Yii::$app->db->getLastInsertID();
        }

        return true;
    }

}
