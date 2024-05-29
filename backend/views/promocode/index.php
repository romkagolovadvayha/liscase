<?php

use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;
use common\models\promocode\Promocode;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $model Promocode */

$this->title = Yii::t('common', 'Промокоды');
?>

<?= Html::a(Yii::t('common', 'Добавить промокод'),
    '/promocode/create',
    ['class' => 'btn btn-success']); ?>
<div>&nbsp;</div>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel'  => $searchModel,
    'columns'      => [
        'code',
        [
            'attribute' => 'status',
            'filterType'  => GridView::FILTER_SELECT2,
            'filter'    => ArrayHelper::merge(['' => 'Все'], Promocode::getStatusList()),
            'options'   => ['width' => '150'],
            'value'     => function (Promocode $model) {
                return ArrayHelper::getValue(Promocode::getStatusList(), $model->status);
            },
        ],
        [
            'attribute' => 'amount',
            'options'   => ['width' => '100'],
        ],
        [
            'attribute' => 'finished_at',
            'options'   => ['width' => '200'],
            'class' => \common\components\grid\DateColumn::class,
        ],
        [
            'class'    => 'yii\grid\ActionColumn',
            'template' => '{update}{delete}',
            'options'  => ['width' => '30'],
        ],
    ],
]);
?>
