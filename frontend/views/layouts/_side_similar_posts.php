<?php

use yii\widgets\ListView;
use common\models\blog\Blog;
use yii\data\ActiveDataProvider;

/** @var Blog $model */
$models = Blog::getSimilarPosts($model->keywords, [$model->id]);

$dataProvider = new ActiveDataProvider([
    'models' => $models,
    'pagination' => false,
    'sort'  => [
        'defaultOrder' => ['created_at' => SORT_DESC],
    ],
]);
?>
<section class="stats-aside__stat-block stat-block">
    <h4 class="stat-block__title"><?=Yii::t('common', 'Похожие записи')?></h4>
    <div class="tab-content">
        <?= ListView::widget([
                                 'id'           => 'blog-popular-list-view',
                                 'options' => [
                                     'class'           => 'stat-block__list',
                                 ],
                                 'dataProvider' => $dataProvider,
                                 'layout'       => "{items}",
                                 'itemView'     => '_side_popular_posts_item',
                                 'itemOptions' => [
                                     'tag' => false,
                                 ],
                             ]) ?>
    </div>
</section>