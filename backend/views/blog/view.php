<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use common\models\blog\Blog;
use yii\helpers\ArrayHelper;

/** @var yii\web\View $this */
/** @var common\models\blog\Blog $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Блог', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
$actionGeneratePosts = Yii::$app->cache->get('actionGeneratePosts');
$actionGeneratePost = Yii::$app->cache->get('actionGenerate_Post_' . $model->id);
?>
<div class="blog-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Изменить', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Are you sure you want to delete this item?',
                'method' => 'post',
            ],
        ]) ?>
        <?php if (empty($actionGeneratePosts) && empty($actionGeneratePost)): ?>
            <?= Html::a('Генерация контента для поста', ['generate-post', 'postId' => $model->id], ['class' => 'btn btn-primary', 'data' => [
                'confirm' => 'Вы уверены?',
                'method' => 'post',
            ]]) ?>
        <?php else: ?>
            <?= Html::a('Идет процес генерации контента', [''], ['class' => 'btn btn-default disabled']) ?>
        <?php endif; ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'name:ntext',
            [
                'attribute' => 'content',
                'format'    => 'raw',
            ],
            'description:ntext',
            'keywords:ntext',
            [
                'attribute' => 'blog_category_id',
                'format'    => 'raw',
                'value'     => function (Blog $model) {
                    return Html::a($model->blogCategory->name, ['/blog-category/view', 'id' => $model->blogCategory->id]);
                },
            ],
            [
                'attribute' => 'link_name',
                'label' => 'Ссылка на пост',
                'format' => 'raw',
                'value'     => function (Blog $model) {
                    $link = Yii::$app->params['baseUrl'] . $model->getUrl();
                    return Html::a($link, $link, ['target' => '_blank']);
                },
            ],
            [
                'attribute' => 'status',
                'value'     => function (Blog $model) {
                    return ArrayHelper::getValue(Blog::getStatusList(), $model->status);
                },
            ],
            'created_at',
        ],
    ]) ?>

</div>
