<?php

use common\models\servers\ServersRules;
use common\models\servers\Servers;
use common\models\servers\ServersRulesCategory;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\grid\ActionColumn;
use kartik\grid\GridView;

/** @var backend\models\ServersRulesSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Правила серверов';
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>
<div class="servers-rules-index w-full">
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
                [
                    'attribute' => 'category_id',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function ($model) {
                        return $model->category ? $model->category->name : '';
                    },
                ],
                [
                    'attribute' => 'server_id',
                    'label' => 'Серверы',
                    'format' => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function ($model) {
                        $servers = $model->servers;
                        if (empty($servers)) return '<span class="ds-badge ds-badge--info">Общее</span>';
                        $serverNames = [];
                        foreach ($servers as $server) {
                            $serverNames[] = '<span class="ds-badge ds-badge--secondary">' . Html::encode($server->name) . '</span>';
                        }
                        return implode(' ', $serverNames);
                    },
                ],
                ['attribute' => 'title', 'format' => 'ntext', 'contentOptions' => ['class' => $bodyCellClass . ' max-w-[200px]'], 'headerOptions' => ['class' => $headerCellClass]],
                [
                    'attribute' => 'content',
                    'format' => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass . ' max-w-[300px]'],
                    'value' => function ($model) {
                        return mb_substr(strip_tags($model->content), 0, 100) . '...';
                    },
                ],
                [
                    'attribute' => 'punishment',
                    'format' => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function ($model) {
                        return $model->punishment ? '<span class="ds-badge ds-badge--warning">' . Html::encode($model->punishment) . '</span>' : '';
                    },
                ],
                ['attribute' => 'sort', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                ['attribute' => 'created_at', 'format' => 'datetime', 'headerOptions' => ['class' => $headerCellClass], 'contentOptions' => ['class' => $bodyCellClass]],
                [
                    'class' => ActionColumn::class,
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'urlCreator' => function ($action, ServersRules $model, $key, $index, $column) {
                        return Url::toRoute([$action, 'id' => $model->id]);
                    },
                ],
            ],
        ]); ?>
    </div>
</div>
