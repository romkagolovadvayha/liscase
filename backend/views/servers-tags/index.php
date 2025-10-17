<?php

use common\models\servers\ServersTags;
use backend\models\ServersTagsSearch;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var backend\models\ServersTagsSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Теги серверов';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="servers-tags-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Создать тег', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'name',
            'title',
            'link_name',
            [
                'attribute' => 'short_description',
                'format' => 'ntext',
                'contentOptions' => ['style' => 'max-width: 300px;'],
            ],
            [
                'attribute' => 'color',
                'format' => 'raw',
                'value' => function($model) {
                    return '<span class="badge" style="background-color: ' . $model->color . '">' . $model->color . '</span>';
                },
            ],
            'sort',
            [
                'attribute' => 'status',
                'value' => function($model) {
                    return $model->getStatusName();
                },
            ],
            'created_at:datetime',

            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, ServersTags $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>


</div>

