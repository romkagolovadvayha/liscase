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

    /** Кэш одной строки blog_category для API/списков, сек. Сброс: {@see invalidateApiRowCache()}, afterSave/afterDelete. */
    public const API_ROW_CACHE_TTL = 300;

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
        
        // Удаляем все недопустимые символы, оставляем только буквы, цифры, пробелы и дефисы
        $text = preg_replace("/[^a-zA-Z0-9\s\-]/", '', $text);
        
        // Заменяем множественные пробелы на один пробел
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Заменяем пробелы на дефисы
        $text = str_replace(' ', '-', $text);
        
        // Заменяем множественные дефисы на один дефис
        $text = preg_replace('/-+/', '-', $text);
        
        // Убираем дефисы в начале и конце строки
        $text = trim($text, '-');

        return $text;
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
        $categories = $query->with(['parentCategory'])->all();

        $result = [];
        foreach ($categories as $category) {
            $result[$category->id] = $category->parentCategory->name . " - " . $category->name;
        }

        return $result;
    }

    public function getParent()
    {
        return $this->hasOne(self::class, ['id' => 'blog_category_id']);
    }

    public function getChildren()
    {
        return $this->hasMany(self::class, ['blog_category_id' => 'id'])
                    ->andWhere(['status' => self::STATUS_ACTIVE])
                    ->orderBy(['created_at' => SORT_DESC]);
    }

    public static function apiRowCacheKey(int $id): string
    {
        return 'blog_category_api_row_v1_' . $id;
    }

    /**
     * Сброс кэша строки категории (после правок в админке).
     */
    public static function invalidateApiRowCache(?int $categoryId = null): void
    {
        if ($categoryId === null || $categoryId <= 0) {
            return;
        }
        Yii::$app->cache->delete(static::apiRowCacheKey($categoryId));
    }

    /**
     * Одна категория из кэша (атрибуты строки), TTL {@see API_ROW_CACHE_TTL}.
     */
    public static function findOneCached(int $id): ?self
    {
        if ($id <= 0) {
            return null;
        }
        $cache = Yii::$app->cache;
        $key = static::apiRowCacheKey($id);
        $cached = $cache->get($key);
        if ($cached !== false && is_array($cached) && isset($cached['attrs']) && is_array($cached['attrs'])) {
            $m = new static();
            $m->setAttributes($cached['attrs'], false);
            $m->setIsNewRecord(false);
            $m->setOldAttributes($cached['attrs']);
            return $m;
        }

        $m = static::find()->where(['id' => $id])->one();
        if ($m === null) {
            return null;
        }
        $cache->set($key, ['attrs' => $m->getAttributes()], static::API_ROW_CACHE_TTL);
        return $m;
    }

    /**
     * {@inheritdoc}
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        static::invalidateApiRowCache((int)$this->id);
        \common\helpers\BlogCacheHelper::invalidateBlogCategoriesApiCache();
        \common\helpers\BlogCacheHelper::invalidateBlogListApiCache();
    }

    /**
     * {@inheritdoc}
     */
    public function afterDelete()
    {
        static::invalidateApiRowCache((int)$this->id);
        \common\helpers\BlogCacheHelper::invalidateBlogCategoriesApiCache();
        \common\helpers\BlogCacheHelper::invalidateBlogListApiCache();
        parent::afterDelete();
    }
}
