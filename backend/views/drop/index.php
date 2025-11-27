<?php

use common\models\box\Drop;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $model Drop */

$this->title = Yii::t('common', 'Предметы');
?>
<div class="drop-index-page">
    <div class="content-header">
        <div class="ds-flex ds-flex--between">
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ds-flex ds-flex--gap-md">
                <?= Html::a('<i class="fas fa-plus"></i> ' . Yii::t('common', 'Добавить предмет'),
                    ['/drop/create'],
                    ['class' => 'ds-btn ds-btn--success']) ?>
                <?= Html::a('<i class="fas fa-sort"></i> ' . Yii::t('common', 'Сортировать'),
                    ['/drop/sort'],
                    ['class' => 'ds-btn ds-btn--primary']) ?>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="ds-card">
            <?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel'  => $searchModel,
    'columns'      => [
        [
            'attribute' => 'id',
            'format'    => 'raw',
            'options'   => ['width' => '70'],
        ],
        [
            'format'    => 'raw',
            'options'   => ['width' => '50'],
            'value'     => function (Drop $model) {
                if (empty($model->imageOrig)) {
                    return null;
                }
                return Html::img($model->imageOrig->getImagePubUrl(false), [
                    'width' => '40px',
                    'height' => '40px',
                    'loading' => 'lazy',
                    'alt' => Html::encode($model->name ?? ''),
                    'style' => 'border-radius: 4px; object-fit: cover;'
                ]);
            },
        ],
        'name',
        [
            'attribute' => 'category_id',
            'filterType'  => GridView::FILTER_SELECT2,
            'filter'    => ArrayHelper::merge(['' => 'Все'], \common\models\box\Category::getCategoryList()),
            'options'   => ['width' => '150'],
            'value'     => function (Drop $model) {
                if (empty($model->category)) {
                    return null;
                }
                return $model->category->name;
            },
        ],
        [
            'attribute' => 'eng_name',
            'options'   => ['width' => '100'],
        ],
        [
            'class'    => 'yii\grid\ActionColumn',
            'template' => '{update}{delete}',
            'options'  => ['width' => '30'],
        ],
    ],
]); ?>
        </div>
    </div>
</div>
