<?php

namespace backend\forms\news;

use Yii;
use yii\helpers\ArrayHelper;
use yii\web\UploadedFile;
use common\models\news\News;
use common\models\news\NewsImage;

class NewsForm extends News
{

    public function rules(): array
    {
        return ArrayHelper::merge([
            [['date_published'], 'trim'],
        ], parent::rules());
    }

    public function attributeLabels(): array
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'date_published' => 'Дата публикации, по НСК (GMT+7)',
        ]);
    }

    /**
     * @return bool
     */
    public function saveRecord()
    {
        if (!$this->validate()) {
            return false;
        }

        $dbTransaction = Yii::$app->db->beginTransaction();

        try {
            if ($this->isNewRecord) {
                $this->status = self::STATUS_PREPARE;
            }

            $this->date_published = date('Y-m-d H:i:00', strtotime($this->date_published));

            if (!$this->save()) {
                throw new \Exception('News not saved');
            }

            $dbTransaction->commit();

            return true;

        } catch (\Exception $e) {
            $this->addError('name', 'Ошибка при сохранении');
        }

        $dbTransaction->rollBack();

        return false;
    }
}
