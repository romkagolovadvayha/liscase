<?php

use kartik\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use common\models\promocode\Promocode;

/** @var $dataProvider */
/** @var $searchModel \common\models\promocode\PromocodeSearch */
/** @var $model Promocode */

$this->title = Yii::t('common', 'Промокоды');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$currentTab = $searchModel->tab ?? '';
$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
$tabClass = 'px-3 py-2 rounded-t text-sm font-medium no-underline transition-colors';
$tabActiveClass = 'bg-[hsl(0_0%_20.4%_/_1)] text-white border-b border-transparent';
$tabInactiveClass = 'text-gray-400 hover:text-white border-b border-transparent';
?>
<div class="promocode-index-page w-full">
    <div class="flex gap-1 border-b border-[hsl(0_0%_15.3%_/_1)] mb-4">
        <?= Html::a(
            Yii::t('common', 'Обычные'),
            ['/promocode/index'],
            ['class' => $tabClass . ' ' . ($currentTab !== 'single' ? $tabActiveClass : $tabInactiveClass)]
        ) ?>
        <?= Html::a(
            Yii::t('common', 'Одноразовые'),
            ['/promocode/index', 'tab' => 'single'],
            ['class' => $tabClass . ' ' . ($currentTab === 'single' ? $tabActiveClass : $tabInactiveClass)]
        ) ?>
    </div>
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
                ['attribute' => 'code', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                [
                    'attribute' => 'status',
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter' => ArrayHelper::merge(['' => 'Все'], Promocode::getStatusList()),
                    'options' => ['width' => '120'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (Promocode $model) {
                        return ArrayHelper::getValue(Promocode::getStatusList(), $model->status);
                    },
                ],
                ['attribute' => 'amount', 'options' => ['width' => '50'], 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                [
                    'attribute' => 'finished_at',
                    'options' => ['width' => '180'],
                    'format' => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (Promocode $model) {
                        if ($model->finished_at === null) {
                            return '<span class="text-gray-500">' . Yii::t('common', 'Бессрочный') . '</span>';
                        }
                        return date('d.m.Y H:i:s', strtotime($model->finished_at));
                    },
                ],
                [
                    'label' => Yii::t('common', 'Кто ввёл'),
                    'options' => ['width' => '140'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (Promocode $model) {
                        return Html::encode($model->getUsedByDisplay());
                    },
                ],
                [
                    'class' => ActionColumn::class,
                    'template' => '{update}',
                    'options' => ['width' => '30'],
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
