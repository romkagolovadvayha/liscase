<?php

use common\models\blog\Blog;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use kartik\grid\GridView;
use yii\helpers\ArrayHelper;

/** @var yii\web\View $this */
/** @var backend\models\blog\BlogSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Блог';
$this->params['breadcrumbs'][] = $this->title;
$cacheData = Yii::$app->cache->get('actionGeneratePosts');
?>
<div class="blog-index">
    <p>
        <?= Html::a('Добавить пост', ['create'], ['class' => 'btn btn-success']) ?>
        <?= Html::a('Категории', ['/blog-category'], ['class' => 'btn btn-success']) ?>

        <?php if (empty($cacheData)): ?>
            <?= Html::a('Генерация постов', ['/blog/generate'], ['class' => 'btn btn-success', 'data' => [
                'confirm' => 'Вы уверены?',
                'method' => 'post',
            ]]) ?>
        <?php else: ?>
            <?= Html::a('Идет процес генерации', [''], ['class' => 'btn btn-default disabled']) ?>
        <?php endif; ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'attribute' => 'id',
                'options'   => ['width' => '50'],
            ],
            'name:ntext',
            [
                'attribute' => 'blog_category_id',
                'options'   => ['width' => '250'],
                'format'    => 'raw',
                'value'     => function (Blog $model) {
                    return Html::a($model->blogCategory->name, ['/blog-category/view', 'id' => $model->blogCategory->id]);
                },
            ],
            [
                'attribute' => 'status',
                'options'   => ['width' => '180'],
                'filterType'  => GridView::FILTER_SELECT2,
                'filter'    => ArrayHelper::merge(['' => 'Любой'], Blog::getStatusList()),
                'value'     => function (Blog $model) {
                    return ArrayHelper::getValue(Blog::getStatusList(), $model->status);
                },
            ],
            [
                'attribute' => 'created_at',
                'options'   => ['width' => '200'],
            ],
            [
                'class' => ActionColumn::className(),
                'options'   => ['width' => '60'],
                'urlCreator' => function ($action, Blog $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>


</div>
