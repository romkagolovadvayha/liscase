<?php

namespace common\models\blog;

use common\components\helpers\ArrayValIterator;
use common\helpers\BlogCacheHelper;
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
 * @property string $update_at
 * @property string $news_id
 *
 * @property BlogCategory $blogCategory
 * @property BlogImage[] $blogImages
 * @property BlogRating[] $blogRatings
 * @property User $user
 * @property CommentModel[] $comments
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

    /**
     * {@inheritdoc}
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        $tracked = ['name', 'description', 'content', 'blog_category_id', 'status', 'link_name', 'created_at', 'update_at', 'keywords'];
        if ($insert || !empty(array_intersect(array_keys($changedAttributes), $tracked))) {
            BlogCacheHelper::invalidateBlogListApiCache();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function afterDelete()
    {
        BlogCacheHelper::invalidateBlogListApiCache();
        parent::afterDelete();
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            if (empty($this->link_name)) {
                $this->link_name = BlogCategory::toLinkName($this->name);
            }
            if (empty($this->user_id)) {
                $this->user_id = 509;
            }
            // При создании устанавливаем update_at = created_at
            if (empty($this->update_at)) {
                $this->update_at = $this->created_at ?: date('Y-m-d H:i:s');
            }
        } else {
            // При обновлении устанавливаем текущую дату
            $this->update_at = date('Y-m-d H:i:s');
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
            [['created_at', 'update_at'], 'safe'],
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
            'link_name' => 'ЧПУ',
            'status' => 'Статус',
            'keywords' => 'Ключевые слова',
            'created_at' => 'Дата создания',
            'update_at' => 'Дата обновления',
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
     * Обрабатывает контент блога и заменяет ссылки на изображения на S3 URL
     * @param string $content
     * @return string
     */
    public function processContentWithS3Images($content) {
        if (empty($content)) {
            return $content;
        }

        $s3PublicUrl = Yii::$app->settings->get('s3_publicUrl');
        if (empty($s3PublicUrl)) {
            return $content;
        }

        // Заменяем /uploads/blog/ на /blog/ в src атрибутах изображений
        $processedContent = preg_replace(
            '/(<img[^>]*src=["\'])(\/uploads\/blog\/)([^"\']+)(["\'])/i',
            '$1' . $s3PublicUrl . '/uploads/blog/$3$4',
            $content
        );

        return $processedContent;
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
    public static function getSimilarPostsFulltext(self $current, int $limit = 5): array
    {
        $tokens = self::tokenize($current->name);
        if (empty($tokens)) return [];

        // Запрос для IN BOOLEAN MODE с префиксами
        $queryStr = implode(' ', array_map(fn($w) => $w.'*', array_slice($tokens, 0, 8)));

        $q = self::find()
                 ->alias('b')
                 ->select([
                              'b.id','b.name','b.blog_category_id','b.created_at','b.views', 'b.link_name',
                              new \yii\db\Expression('MATCH (b.name, b.description, b.content) AGAINST (:q IN BOOLEAN MODE) AS score'),
                          ])
                 ->where(['b.status' => self::STATUS_ACTIVE])
                 ->andWhere(['<>','b.id',$current->id])
                 ->andWhere(new \yii\db\Expression('MATCH (b.name, b.description, b.content) AGAINST (:q IN BOOLEAN MODE)'))
                 ->params([':q' => $queryStr]);

        if ($current->blog_category_id) {
            $q->andWhere(['b.blog_category_id' => $current->blog_category_id]);
        }

        return $q->orderBy(['score' => SORT_DESC, 'created_at' => SORT_DESC])
                 ->limit($limit)
                 ->all();
    }

    /** Токенизация: чистим, нормализуем, выкидываем стоп-слова и коротыши */
    private static function tokenize(string $text): array
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace('ё','е',$text);
        // буквы/цифры/дефис/пробел, всё остальное — в пробел
        $text = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $words = array_unique(array_filter(array_map('trim', explode(' ', $text)), function($w){
            return mb_strlen($w, 'UTF-8') >= 3;
        }));

        // стоп-слова (ru + общие)
        $stop = [
            'без','близ','в','во','вместо','вне','для','до','за','и','из','изо','под','к','ко','кроме','между','на','над','о','об','обо','от','ото','перед','передо','пред','предо','по','подо','при','про','ради','с','со','сквозь','среди','у','через','но','или',
            // частые мусорные
            'это','как','так','чтоб','чтобы','быть','есть','будет','тут','там','тема','про','по','надо'
        ];

        return array_values(array_diff($words, $stop));
    }

    /**
     * Gets query for [[Comments]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getComments() {
        return $this->hasMany(CommentModel::class, ['entityId' => 'id']);
    }
}
