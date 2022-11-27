<?php

namespace backend\forms\news;

use Yii;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;
use common\models\news\NewsContent;

class NewsContentForm extends NewsContent
{
    public function rules()
    {
        return ArrayHelper::merge([
            [['title', 'title_text', 'body'], 'trim'],
            [['title', 'title_text', 'body'], 'required'],
            [
                ['language'],
                'unique',
                'targetAttribute' => ['news_id', 'language'],
                'message'         => 'Выбранный язык уже настроен для данной новости',
            ],
        ], parent::rules());
    }

    public function beforeValidate()
    {
        $this->body = str_replace('????', '', $this->body);

        return parent::beforeValidate();
    }

    /**
     * @return bool
     */
    public function saveRecord()
    {
        if (!$this->validate()) {
            return false;
        }

        return $this->save();
    }

}
