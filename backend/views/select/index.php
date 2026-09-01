<?php

use common\models\box\Select;
use backend\components\AccessibleKartikGridView as GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $model Select */

$this->title = Yii::t('common', 'Наборы с выбором');
?>
<div class="select-index-page">
    <div class="content-header">
        <div class="ds-flex ds-flex--between">
            <h1><?= Html::encode($this->title) ?></h1>
            <?= Html::a('<i class="fas fa-plus"></i> ' . Yii::t('common', 'Добавить набор с выбором'),
                ['/select/create'],
                ['class' => 'ds-btn ds-btn--success']) ?>
        </div>
    </div>

    <div class="content">
        <div class="ds-card">
            <?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel'  => $searchModel,
    'columns'      => [
        [
            'format'    => 'raw',
            'options'   => ['width' => '50'],
            'value'     => function (Select $model) {
                if (empty($model->imageOrig)) {
                    return null;
                }
                return Html::img($model->imageOrig->getImagePubUrl(), [
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
            'attribute' => 'status',
            'options'   => ['width' => '130'],
            'filterType'  => GridView::FILTER_SELECT2,
            'filter'    => Select::getStatusList(),
            'format'    => 'raw',
            'value'     => function (Select $model) {
                $status = ArrayHelper::getValue(Select::getStatusList(), $model->status);
                $badgeClass = $model->status == Select::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
                return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
            },
        ],
        [
            'options'   => ['width' => '200'],
            'class' => \common\components\grid\DateColumn::class,
        ],
        [
            'class'    => 'yii\grid\ActionColumn',
            'template' => '{update} {delete}',
            'options'  => ['width' => '45'],
        ],
    ],
]);
?>
