<?php

namespace common\models\news;

use Yii;

/**
 * This is the model class for table "news_content".
 *
 * @property int    $id
 * @property int    $news_id
 * @property string $language
 * @property string $title
 * @property string $title_text
 * @property string $body
 * @property string $created_at
 *
 * @property News   $news
 */
class NewsContent extends \common\components\base\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'news_content';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['news_id', 'title', 'body'], 'required'],
            [['news_id'], 'integer'],
            [['title', 'title_text', 'body'], 'string'],
            [['language'], 'string', 'max' => 5],
            [['created_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'language'   => 'Язык',
            'main_video' => 'Ссылка на Youtube-видео',
            'title'      => 'Заголовок новости',
            'title_text' => 'Короткий текст',
            'body'       => 'Текст новости',
            'created_at' => 'Дата создания',
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
     * @return array
     */
    public function getLanguageList()
    {
        return \common\components\web\Language::getLanguageList();
    }
}
