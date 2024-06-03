<?php

namespace common\models\blog;

use common\components\helpers\ArrayValIterator;
use common\models\user\User;
use Yii;
use yii2mod\comments\models\CommentModel;

/**
 * This is the model class for table "blog".
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $content
 * @property int $views
 * @property string $description
 * @property string $keywords
 * @property int $blog_category_id
 * @property string $link_name
 * @property int $status
 * @property string $created_at
 *
 * @property BlogCategory $blogCategory
 * @property BlogImage[] $blogImages
 * @property BlogRating[] $blogRatings
 * @property User $user
 */
class Blog extends \yii\db\ActiveRecord
{

    const STATUS_NOT_ACTIVE = 0;
    const STATUS_ACTIVE = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'blog';
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            if (empty($this->link_name)) {
                $this->link_name = BlogCategory::toLinkName($this->name);
            }
        }

        return parent::beforeSave($insert);
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'blog_category_id', 'link_name', 'status'], 'required'],
            [['name', 'description', 'content', 'link_name', 'keywords'], 'string'],
            [['blog_category_id', 'status', 'views'], 'integer'],
            [['created_at'], 'safe'],
            [['blog_category_id'], 'exist', 'skipOnError' => true, 'targetClass' => BlogCategory::class, 'targetAttribute' => ['blog_category_id' => 'id']],
        ];
    }

    /**
     * @return array
     */
    public static function getStatusList(): array
    {
        return [
            self::STATUS_NOT_ACTIVE       => Yii::t('common', 'Не активный'),
            self::STATUS_ACTIVE       => Yii::t('common', 'Активный'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Название',
            'content' => 'Текст',
            'description' => 'Описание',
            'blog_category_id' => 'Категория',
            'link_name' => 'Code',
            'status' => 'Статус',
            'keywords' => 'Ключевые слова',
            'created_at' => 'Дата создания',
        ];
    }

    /**
     * Gets query for [[BlogCategory]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBlogCategory()
    {
        return $this->hasOne(BlogCategory::class, ['id' => 'blog_category_id']);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Gets query for [[BlogImages]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBlogImages()
    {
        return $this->hasMany(BlogImage::class, ['blog_id' => 'id']);
    }

    /**
     * Gets query for [[BlogRatings]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBlogRatings()
    {
        return $this->hasMany(BlogRating::class, ['blog_id' => 'id']);
    }

    /**
     * @return string
     */
    public function getUrl() {
        return "{$this->blogCategory->getUrl()}/post-{$this->link_name}";
    }

    /**
     * @param      $categoryLinkName
     * @param      $blogLinkName
     * @param null $categoryLinkNameChild
     *
     * @return bool
     */
    public function checkUrl($categoryLinkName, $blogLinkName, $categoryLinkNameChild = null) {
        if (!empty($categoryLinkNameChild)) {
            return "/posts/{$categoryLinkName}/{$categoryLinkNameChild}/post-{$blogLinkName}" === $this->getUrl();
        }
        return "/posts/{$categoryLinkName}/post-{$blogLinkName}" === $this->getUrl();
    }

    /**
     * @param       $text
     * @param array $dontIds
     *
     * @return array
     */
    public static function getSimilarPosts($text, $dontIds = []) {
        $text = ArrayValIterator::getMinificationArray($text);
        $count = count($text);
        /** @var Blog[] $list */
        $query = Blog::find()->cache(60)->andWhere(['status' => Blog::STATUS_ACTIVE]);
        if (!empty($dontIds)) {
            $query->andWhere(['NOT IN', 'id', $dontIds]);
        }
        $list = $query->all();
        $similars = [];
        foreach($list as $model) {
            $verifiable = ArrayValIterator::getMinificationArray($model->keywords);
            $similar_counter = 0;
            foreach ($text as $text_row) {
                foreach ($verifiable as $verifiable_row){
                    if($text_row == $verifiable_row) {
                        $similar_counter++;
                        break;
                    }
                }
            }
            if ($similar_counter === 0) {
                continue;
            }
            $similars[] = ['id' => $model->id, 'proc' => $similar_counter * 100 / $count];
        }
        usort($similars, create_function('$a,$b', 'return -($a["proc"] - $b["proc"]);'));
        $results = [];
        foreach ($similars as $item) {
            $results[] = Blog::findOne($item['id']);
            if (count($results) >= 5) {
                break;
            }
        }
        return $results;
    }

    /**
     * @return bool|int|string|null
     */
    public function getCountComments() {
        return CommentModel::find()->andWhere(['entityId' => $this->id])->count();
    }
}
