<?php

use common\models\box\Box;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\grid\ActionColumn;

/** @var $dataProvider */
/** @var $searchModel \common\models\box\BoxSearch */
/** @var $model Box */

$this->title = Yii::t('common', 'Кейсы');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>
<div class="box-index-page w-full">
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
                    'format' => 'raw',
                    'options' => ['width' => '50'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (Box $model) {
                        if (empty($model->imageOrig)) return null;
                        return Html::img($model->imageOrig->getImagePubUrl(false), [
                            'width' => '40px', 'height' => '40px', 'loading' => 'lazy',
                            'alt' => Html::encode($model->name ?? ''),
                            'style' => 'border-radius: 4px; object-fit: cover;',
                        ]);
                    },
                ],
                ['attribute' => 'name', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                ['attribute' => 'price', 'options' => ['width' => '130'], 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                [
                    'attribute' => 'status',
                    'options' => ['width' => '130'],
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter' => Box::getStatusList(),
                    'format' => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (Box $model) {
                        $status = ArrayHelper::getValue(Box::getStatusList(), $model->status);
                        $badgeClass = $model->status == Box::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
                        return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
                    },
                ],
                [
                    'attribute' => 'type',
                    'options' => ['width' => '180'],
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter' => Box::getTypeList(),
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (Box $model) {
                        return ArrayHelper::getValue(Box::getTypeList(), $model->type);
                    },
                ],
                [
                    'attribute' => 'created_at',
                    'options' => ['width' => '200'],
                    'class' => \common\components\grid\DateColumn::class,
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'class' => ActionColumn::class,
                    'template' => '{update} {delete}',
                    'options' => ['width' => '45'],
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
