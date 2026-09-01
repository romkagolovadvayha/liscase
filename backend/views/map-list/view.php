<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var common\models\map\MapList $model */

$this->title = 'Карта №' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Карты', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="map-list-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Изменить', ['update', 'id' => $model->id], ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
            'class' => 'ds-btn ds-btn--danger',
            'data' => [
                'confirm' => 'Удалить эту карту?',
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
                        return Html::img($model->image_preview, [
                            'class' => 'admin-map-preview admin-map-preview--large',
                            'alt' => 'Карта ' . Html::encode($model->hash ?? ''),
                        ]);
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
        <h3>Монументы</h3>
        <pre><?= Html::encode($model->monuments_json) ?></pre>
    <?php endif; ?>

    <?php if ($model->biome_percentages_json): ?>
        <h3>Распределение биомов</h3>
        <pre><?= Html::encode($model->biome_percentages_json) ?></pre>
    <?php endif; ?>

    <?php if ($model->data_json): ?>
        <h3>Полные данные</h3>
        <pre class="admin-code-preview"><?= Html::encode($model->data_json) ?></pre>
    <?php endif; ?>

</div>

