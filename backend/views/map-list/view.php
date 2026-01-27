<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var common\models\map\MapList $model */

$this->title = 'Map List #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Map List', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="map-list-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Update', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Delete', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Are you sure you want to delete this item?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'hash',
            'url:url',
            [
                'attribute' => 'image_preview',
                'format' => 'raw',
                'value' => function ($model) {
                    if ($model->image_preview) {
                        return Html::img($model->image_preview, ['style' => 'max-width: 300px; max-height: 300px;']);
                    }
                    return '-';
                },
            ],
            'size',
            'size_int',
            'map_type',
            'seed',
            'save_version',
            [
                'attribute' => 'is_staging',
                'format' => 'boolean',
            ],
            [
                'attribute' => 'is_custom_map',
                'format' => 'boolean',
            ],
            [
                'attribute' => 'can_download',
                'format' => 'boolean',
            ],
            'total_monuments',
            'land_percentage',
            'islands',
            'mountains',
            'ice_lakes',
            'rivers',
            'lakes',
            'canyons',
            'oases',
            'buildable_rocks',
            'raw_image_url:url',
            'image_url:url',
            'image_icon_url:url',
            'thumbnail_url:url',
            'created_at:datetime',
        ],
    ]) ?>

    <?php if ($model->monuments_json): ?>
        <h3>Monuments</h3>
        <pre><?= Html::encode($model->monuments_json) ?></pre>
    <?php endif; ?>

    <?php if ($model->biome_percentages_json): ?>
        <h3>Biome Percentages</h3>
        <pre><?= Html::encode($model->biome_percentages_json) ?></pre>
    <?php endif; ?>

    <?php if ($model->data_json): ?>
        <h3>Full Data</h3>
        <pre style="max-height: 400px; overflow: auto;"><?= Html::encode($model->data_json) ?></pre>
    <?php endif; ?>

</div>

