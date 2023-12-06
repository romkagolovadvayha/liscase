<?php

namespace common\models\news;

use Yii;

/**
 * This is the model class for table "news_image".
 *
 * @property int    $id
 * @property int    $news_id
 * @property int    $type
 * @property string $image
 * @property string $created_at
 *
 * @property News   $news
 */
class NewsImage extends \common\components\base\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'news_image';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['news_id', 'image'], 'required'],
            [['news_id', 'type'], 'integer'],
            [['image'], 'string'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * Gets query for [[News]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getNews()
    {
        return $this->hasOne(News::class, ['id' => 'news_id']);
    }

    /**
     * @param int    $newsId
     * @param string $fileName
     */
    public static function createRecord($newsId, $fileName)
    {
        $model = new self();

        $model->news_id = $newsId;
        $model->image   = $fileName;

        $model->save();
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return Yii::$app->params['homePage'] . '/uploads/news/' . $this->image;
    }

    /**
     * @param null $fileName
     *
     * @return string
     */
    public function getPath($fileName = null)
    {
        if (empty($fileName)) {
            $fileName = $this->image;
        }

        return Yii::$app->getBasePath() . '/web/uploads/news/' . $fileName;
    }
}
