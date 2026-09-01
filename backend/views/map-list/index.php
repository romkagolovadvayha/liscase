<?php

use common\models\map\MapList;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use backend\components\AccessibleGridView as GridView;

/** @var yii\web\View $this */
/** @var backend\models\map\MapListSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Карты';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="map-list-index-page">
    <div class="content-header">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="content">
        <div class="ds-card">
            <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'hash',
            [
                'attribute' => 'image_preview',
                'format' => 'raw',
                'value' => function ($model) {
                    if ($model->image_preview) {
                        return Html::img($model->image_preview, [
                            'class' => 'admin-map-preview',
                            'loading' => 'lazy',
                            'width' => 100,
                            'height' => 100,
                            'alt' => 'Карта ' . Html::encode($model->hash ?? '')
                        ]);
                    }
                    return '-';
                },
            ],
            'size_int',
            'seed',
            'map_type',
            [
                'attribute' => 'is_staging',
                'format' => 'boolean',
            ],
            [
                'attribute' => 'is_custom_map',
                'format' => 'boolean',
            ],
            'total_monuments',
            'created_at:datetime',
            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, MapList $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>
        </div>
    </div>
</div>

