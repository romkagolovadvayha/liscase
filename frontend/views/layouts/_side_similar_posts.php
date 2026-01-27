<?php
use yii\widgets\ListView;
use yii\data\ArrayDataProvider;
/** @var \common\models\blog\Blog $model */

$models = \common\models\blog\Blog::getSimilarPostsFulltext($model, 5);

if (empty($models)) {
    return;
}

$dataProvider = new ArrayDataProvider([
    'allModels'  => $models,
    'pagination' => false,
]);
?>

<section class="blog-similar-posts">
    <div class="blog-similar-posts_header">
        <i class="fas fa-th-large"></i>
        <h2><?= Yii::t('common', 'Похожие записи') ?></h2>
    </div>
    
    <div class="blog-similar-posts_grid">
        <?= ListView::widget([
            'id'           => 'blog-similar-list',
            'dataProvider' => $dataProvider,
            'layout'       => '<div class="masonry">{items}</div>',
            'itemView'     => '@frontend/views/blog/_item',
            'itemOptions'  => ['tag' => 'div', 'class' => 'masonry-item'],
            'options'      => ['tag' => false],
        ]) ?>
    </div>
</section>
