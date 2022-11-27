<?php

use common\models\box\Box;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $model Box */

$this->title = Yii::t('common', 'Кейсы');
?>

<?= Html::a(Yii::t('common', 'Добавить кейс'),
    '/box/create',
    ['class' => 'btn btn-success']); ?>
<div>&nbsp;</div>

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
                return Html::img($model->imageOrig->getImagePubUrl(), ['width' => '40px']);
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
            'value'     => function (Box $model) {
                return ArrayHelper::getValue(Box::getStatusList(), $model->status);
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
