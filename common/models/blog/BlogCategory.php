<?php

namespace common\models\blog;

use common\components\google\TranslateApi;
use Yii;

/**
 * This is the model class for table "blog_category".
 *
 * @property int $id
 * @property string $name
 * @property int $blog_category_id
 * @property string $link_name
 * @property string $description
 * @property string $keywords
 * @property int $status
 * @property string $created_at
 *
 * @property Blog[] $blogs
 * @property BlogCategory $parentCategory
 * @property BlogCategory[] $childCategories
 */
class BlogCategory extends \yii\db\ActiveRecord
{

    const STATUS_NOT_ACTIVE = 0;
    const STATUS_ACTIVE = 1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'blog_category';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'description'], 'required'],
            [['status', 'blog_category_id'], 'integer'],
            [['name', 'link_name', 'description', 'keywords'], 'string'],
            [['created_at'], 'safe'],
        ];
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            if (empty($this->link_name)) {
                $this->link_name = $this->toLinkName($this->name);
            }
            $this->created_at = date('Y-m-d H:i:s');
        }

        return parent::beforeSave($insert);
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
            'link_name' => 'code',
            'description' => 'Описание',
            'status' => 'Статус',
            'created_at' => 'Дата создания',
            'keywords' => 'Ключевые слова',
            'blog_category_id' => 'Родительская категория',
        ];
    }

    /**
     * Gets query for [[ParentCategories]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getParentCategory()
    {
        return $this->hasOne(BlogCategory::class, ['id' => 'blog_category_id']);
    }

    /**
     * Gets query for [[ChildCategories]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getChildCategories()
    {
        return $this->hasMany(BlogCategory::class, ['blog_category_id' => 'id']);
    }

    /**
     * Gets query for [[Blogs]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBlogs()
    {
        return $this->hasMany(Blog::class, ['blog_category_id' => 'id']);
    }

    /**
     * @return string
     */
    public function getUrl() {
        if (!empty($this->parentCategory)) {
            return "{$this->parentCategory->getUrl()}/{$this->link_name}";
        }
        return "/posts/{$this->link_name}";
    }

    /**
     * @param      $categoryLinkName
     * @param      $blogLinkName
     * @param null $categoryLinkNameChild
     *
     * @return bool
     */
    public function checkUrl($categoryLinkName, $categoryLinkNameChild = null) {
        if (!empty($categoryLinkNameChild)) {
            return "/posts/{$categoryLinkName}/{$categoryLinkNameChild}" === $this->getUrl();
        }
        return "/posts/{$categoryLinkName}" === $this->getUrl();
    }

    /**
     * @param $name
     *
     * @return array|mixed|string|string[]
     */
    public static function toLinkName($name) {
        $google = new TranslateApi();
        $text = $google->translateText($name, 'en');
        $text = strtolower($text);
        $text = str_replace(' ', '-', $text);

        return preg_replace( "/[^a-zA-Z_\-0-9\s]/", '', $text);
    }

    /**
     * @return array
     */
    public static function getCategories() {
        $categories = BlogCategory::find()->andWhere([
            'blog_category_id' => null,
        ])->all();

        $result = [];
        foreach ($categories as $category) {
            $result[$category->id] = $category->name;
        }

        return $result;
    }

    /**
     * @return array
     */
    public static function getChildsCategories($id = null) {
        /** @var BlogCategory[] $categories */
        $query = BlogCategory::find()->andWhere('blog_category_id IS NOT NULL');
        if (!empty($id)) {
            $query->andWhere(['blog_category_id' => $id]);
        }
        $categories = $query->all();

        $result = [];
        foreach ($categories as $category) {
            $result[$category->id] = $category->parentCategory->name . " - " . $category->name;
        }

        return $result;
    }
}
