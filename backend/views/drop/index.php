<?php

use common\models\box\Drop;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $model Drop */

$this->title = Yii::t('common', 'Дроп');
?>

<?= Html::a(Yii::t('common', 'Добавить дроп'),
    '/drop/create',
    ['class' => 'btn btn-success']); ?>
<div>&nbsp;</div>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel'  => $searchModel,
    'columns'      => [
        [
            'format'    => 'raw',
            'options'   => ['width' => '50'],
            'value'     => function (Drop $model) {
                if (empty($model->imageOrig)) {
                    return null;
                }
                return Html::img($model->imageOrig->getImagePubUrl(), ['width' => '40px']);
            },
        ],
        'name',
        [
            'attribute' => 'type_id',
            'filterType'  => GridView::FILTER_SELECT2,
            'filter'    => \common\models\box\DropType::getTypeList(),
            'options'   => ['width' => '150'],
            'value'     => function (Drop $model) {
                return $model->type->name;
            },
        ],
        [
            'attribute' => 'price',
            'options'   => ['width' => '100'],
        ],
        [
            'class'    => 'yii\grid\ActionColumn',
            'template' => '{delete}',
            'options'  => ['width' => '30'],
        ],
    ],
]);
?>
