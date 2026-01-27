<?php

use common\models\box\Box;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $model Box */

$this->title = Yii::t('common', 'Кейсы');
?>
<div class="box-index-page">
    <div class="content-header">
        <div class="ds-flex ds-flex--between">
            <h1><?= Html::encode($this->title) ?></h1>
            <?= Html::a('<i class="fas fa-plus"></i> ' . Yii::t('common', 'Добавить кейс'),
                ['/box/create'],
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
            'value'     => function (Box $model) {
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
            'attribute' => 'price',
            'options'   => ['width' => '130'],
        ],
        [
            'attribute' => 'status',
            'options'   => ['width' => '130'],
            'filterType'  => GridView::FILTER_SELECT2,
            'filter'    => Box::getStatusList(),
            'format'    => 'raw',
            'value'     => function (Box $model) {
                $status = ArrayHelper::getValue(Box::getStatusList(), $model->status);
                $badgeClass = $model->status == Box::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
                return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
            },
        ],
        [
            'attribute' => 'type',
            'options'   => ['width' => '180'],
            'filterType'  => GridView::FILTER_SELECT2,
            'filter'    => Box::getTypeList(),
            'value'     => function (Box $model) {
                return ArrayHelper::getValue(Box::getTypeList(), $model->type);
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
