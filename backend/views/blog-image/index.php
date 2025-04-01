<?php

use common\models\blog\BlogImage;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use common\models\blog\BlogImageSearch;

/** @var yii\web\View $this */
/** @var BlogImageSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Blog Images';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="blog-image-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Create Blog Image', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'attribute' => 'id',
                'options'   => ['width' => '50'],
            ],
            [
                'attribute' => 'link',
                'options'   => ['width' => '80'],
                'format'    => 'raw',
                'value'     => function (BlogImage $model) {
                    return $this->render('_file', [
                        'url' => $model->getPublicUrl(),
                        'name' => $model->link
                    ]);
                },
            ],
            [
                'attribute' => 'blog_id',
                'format'    => 'raw',
                'value'     => function (BlogImage $model) {
                    return $model->blog->name;
                },
            ],
            [
                'attribute' => 'created_at',
                'options'   => ['width' => '200'],
            ],
            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, BlogImage $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>


</div>
