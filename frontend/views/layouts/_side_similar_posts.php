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

<section class="block">
    <header class="block_header">
        <div class="block_header_container">
            <h2 class="block_header_container_title"><?=Yii::t('common', 'Похожие записи')?></h2>
        </div>
    </header>
    <div class="block_body">
        <ul class="block_body_article_list">
            <?= ListView::widget([
                'id'           => 'blog-popular-list-view',
                'dataProvider' => $dataProvider,
                'layout'       => "{items}",
                'itemView'     => '_side_popular_posts_item',
                'itemOptions' => [
                    'tag' => false,
                ],
            ]) ?>
        </ul>
    </div>
</section>