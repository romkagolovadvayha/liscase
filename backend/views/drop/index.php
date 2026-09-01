<?php

use common\models\box\Drop;
use common\models\box\Category;
use backend\components\AccessibleKartikGridView as GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\grid\ActionColumn;

/** @var $dataProvider */
/** @var $searchModel \common\models\box\DropSearch */
/** @var $model Drop */

$this->title = Yii::t('common', 'Предметы');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>
<div class="drop-index-page w-full">
    <div class="w-full">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
            'tableOptions' => ['class' => 'table-auto w-full text-sm'],
            'options' => ['class' => 'admin-grid-view-dark'],
            'layout' => "{items}\n{pager}",
            'filterRowOptions' => ['style' => 'display: none;'],
            'bordered' => false,
            'striped' => false,
            'hover' => true,
            'columns' => [
                [
                    'attribute' => 'id',
                    'format' => 'raw',
                    'options' => ['width' => '90'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'format' => 'raw',
                    'label' => '',
                    'options' => ['width' => '60'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass . ' drop-index-preview-cell'],
                    'value' => function (Drop $model) {
                        $url = $model->image();
                        if (!$url) {
                            return '<span class="drop-index-preview-placeholder">—</span>';
                        }
                        return Html::tag('div', Html::img($url, [
                            'width' => 48,
                            'height' => 48,
                            'loading' => 'lazy',
                            'alt' => Html::encode($model->name ?? ''),
                            'class' => 'drop-index-preview-img',
                        ]), ['class' => 'drop-index-preview']);
                    },
                ],
                ['attribute' => 'name', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                [
                    'attribute' => 'category_id',
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter' => ArrayHelper::merge(['' => 'Все'], Category::getCategoryList()),
                    'options' => ['width' => '150'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (Drop $model) {
                        return $model->category ? $model->category->name : null;
                    },
                ],
                ['attribute' => 'eng_name', 'options' => ['width' => '100'], 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                [
                    'class' => ActionColumn::class,
                    'template' => '{update} {delete}',
                    'options' => ['width' => '90'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'urlCreator' => function ($action, $model, $key, $index, $column) {
                        return Url::toRoute([$action, 'id' => $model->id]);
                    },
                ],
            ],
        ]); ?>
    </div>
</div>
