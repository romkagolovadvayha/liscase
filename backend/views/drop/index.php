<?php

use common\models\box\Drop;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

/** @var $dataProvider */
/** @var $searchModel */
/** @var $model Drop */

$this->title = Yii::t('common', 'Предметы');
?>

<?= Html::a(Yii::t('common', 'Добавить предмет'),
    '/drop/create',
    ['class' => 'btn btn-success']); ?> <?= Html::a(Yii::t('common', 'Сортировать'),
    '/drop/sort',
    ['class' => 'btn btn-primary']); ?>
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
                return Html::img($model->imageOrig->getImagePubUrl(), ['width' => '40px']);
            },
        ],
        'name',
        [
            'attribute' => 'category_id',
            'filterType'  => GridView::FILTER_SELECT2,
            'filter'    => ArrayHelper::merge(['' => 'Все'], \common\models\box\Category::getCategoryList()),
            'options'   => ['width' => '150'],
            'value'     => function (Drop $model) {
                return $model->type->name;
            },
        ],
        [
            'attribute' => 'sort',
            'options'   => ['width' => '100'],
        ],
        [
            'class'    => 'yii\grid\ActionColumn',
            'template' => '{update}{delete}',
            'options'  => ['width' => '30'],
        ],
    ],
]);
?>
