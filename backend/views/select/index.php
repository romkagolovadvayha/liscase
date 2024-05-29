<?php

use common\models\box\Select;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $model Select */

$this->title = Yii::t('common', 'Кейсы');
?>

<?= Html::a(Yii::t('common', 'Добавить набор с выбором'),
    '/select/create',
    ['class' => 'btn btn-success']); ?>
<div>&nbsp;</div>

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
                return Html::img($model->imageOrig->getImagePubUrl(), ['width' => '40px']);
            },
        ],
        'name',
        [
            'attribute' => 'status',
            'options'   => ['width' => '130'],
            'filterType'  => GridView::FILTER_SELECT2,
            'filter'    => Select::getStatusList(),
            'value'     => function (Select $model) {
                return ArrayHelper::getValue(Select::getStatusList(), $model->status);
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
