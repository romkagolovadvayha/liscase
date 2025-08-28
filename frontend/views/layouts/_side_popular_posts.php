<?php

use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\caching\DbDependency;
use common\models\blog\Blog;

/** @var Blog|null $current — текущая запись, если есть */

$query = Blog::find()->alias('b')
             ->select(['b.id','b.slug','b.title','b.preview_image_id','b.published_at','b.views','b.blog_category_id'])
             ->where(['b.status' => Blog::STATUS_ACTIVE])
             ->andWhere(['>', 'b.views', 0]);

if (!empty($current) && $current instanceof Blog) {
    // исключаем текущую и берём из той же категории
    $query->andWhere(['<>','b.id',$current->id])
          ->andWhere(['b.blog_category_id' => $current->blog_category_id]);
}

// подгрузите нужные отношения из itemView (пример):
$query->with(['category', 'previewImage']);

// Ранжирование: чуть «нагибаем» к новым и популярным
$query->orderBy(new Expression(' (LOG10(b.views + 1) + UNIX_TIMESTAMP(b.published_at)/864000) DESC '));

// Лёгкая «вариативность» без RAND(): случайная страница среди топа
$pageSize = 5;
$total = (clone $query)->count('*');
$maxPage = max(0, (int)floor(($total - 1) / $pageSize));
$page = $maxPage > 0 ? random_int(0, $maxPage) : 0;

$dataProvider = new ActiveDataProvider([
                                           'query' => $query,
                                           'pagination' => ['pageSize' => $pageSize, 'page' => $page, 'validatePage' => false],
                                           'sort' => false,
                                       ]);

?>
<?php if ($this->beginCache('sidebar-read-now', [
    'duration' => 300,
    'dependency' => new DbDependency([
                                         'sql' => 'SELECT CONCAT(MAX(updated_at),"-",COUNT(*)) FROM blog WHERE status = :st',
                                         'params' => [':st' => Blog::STATUS_ACTIVE],
                                     ]),
])): ?>
    <section class="block">
        <header class="block_header">
            <div class="block_header_container">
                <h2 class="block_header_container_title"><?= Yii::t('common', 'Читают сейчас') ?></h2>
            </div>
        </header>
        <div class="block_body">
            <ul class="stat-block__list">
                <?= yii\widgets\ListView::widget([
                                                     'id'           => 'blog-popular-list-view',
                                                     'dataProvider' => $dataProvider,
                                                     'layout'       => "{items}",
                                                     'itemView'     => '_side_popular_posts_item',
                                                     'itemOptions'  => ['tag' => false],
                                                 ]) ?>
            </ul>
        </div>
    </section>
    <?php $this->endCache(); endif; ?>
