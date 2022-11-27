<?php

namespace common\models\news;

use Yii;
use common\components\base\ActiveRecord;

/**
 * @property int           $id
 * @property int           $name
 * @property int           $status
 * @property string        $created_at
 * @property string        $date_published
 *
 * @property NewsContent[] $newsContents
 * @property NewsImage[]   $newsImages
 */
class News extends ActiveRecord
{
    const STATUS_PREPARE = 1;
    const STATUS_ACTIVE  = 2;
    const STATUS_DELETED = 3;

    /**
     * @return array
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_PREPARE => 'Подготовка',
            self::STATUS_ACTIVE  => 'Опубликована',
            self::STATUS_DELETED => 'Удалена',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'news';
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['status'], 'integer'],
            [['created_at', 'date_published'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id'             => 'ID',
            'name'           => Yii::t('common', 'Название'),
            'status'         => Yii::t('common', 'Статус'),
            'created_at'     => Yii::t('common', 'Дата создания'),
            'date_published' => Yii::t('common', 'Дата публикации'),
        ];
    }

    /**
     * Gets query for [[NewsContents]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getNewsContents()
    {
        return $this->hasMany(NewsContent::class, ['news_id' => 'id']);
    }

    /**
     * Gets query for [[NewsImages]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getNewsImages()
    {
        return $this->hasMany(NewsImage::class, ['news_id' => 'id']);
    }

    /**
     * @param string $language
     *
     * @return NewsContent|null
     */
    public function getContentModel($language)
    {
        return $this
            ->getNewsContents()
            ->andWhere(['language' => $language])
            ->one();
    }
}
