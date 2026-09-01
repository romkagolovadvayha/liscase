<?php

use common\models\servers\ServersRulesCategory;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use backend\components\AccessibleKartikGridView as GridView;

/** @var backend\models\ServersRulesCategorySearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Категории правил';
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>
<div class="servers-rules-category-index w-full">
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
                ['attribute' => 'id', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                ['attribute' => 'name', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                ['attribute' => 'icon', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                ['attribute' => 'sort', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                ['attribute' => 'created_at', 'format' => 'datetime', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                [
                    'class' => ActionColumn::class,
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'urlCreator' => function ($action, ServersRulesCategory $model, $key, $index, $column) {
                        return Url::toRoute([$action, 'id' => $model->id]);
                    },
                ],
            ],
        ]); ?>
    </div>
</div>
