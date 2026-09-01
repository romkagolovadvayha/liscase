<?php

use backend\models\ServersTagsSearch;
use common\models\servers\ServersTags;
use backend\components\AccessibleKartikGridView as GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\grid\ActionColumn;

/** @var ServersTagsSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Теги серверов');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>
<div class="servers-tags-index-page w-full">
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
                    'options' => ['width' => '80'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'attribute' => 'name',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'attribute' => 'title',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'attribute' => 'link_name',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'attribute' => 'short_description',
                    'format' => 'ntext',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass . ' max-w-[200px] truncate'],
                ],
                [
                    'attribute' => 'color',
                    'format' => 'raw',
                    'options' => ['width' => '100'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (ServersTags $model) {
                        if (empty($model->color)) {
                            return '—';
                        }
                        return Html::tag('span', Html::encode($model->color), [
                            'class' => 'inline-block w-6 h-6 rounded border border-white/20',
                            'style' => 'background-color: ' . Html::encode($model->color),
                            'title' => Html::encode($model->color),
                        ]);
                    },
                ],
                [
                    'attribute' => 'sort',
                    'options' => ['width' => '80'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'attribute' => 'status',
                    'options' => ['width' => '120'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter' => ArrayHelper::merge(['' => 'Любой'], ServersTags::getStatusList()),
                    'format' => 'raw',
                    'value' => function (ServersTags $model) {
                        $statusList = ServersTags::getStatusList();
                        $status = ArrayHelper::getValue($statusList, $model->status, '');
                        $badgeClass = $model->status == ServersTags::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
                        return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
                    },
                ],
                [
                    'attribute' => 'created_at',
                    'format' => 'datetime',
                    'options' => ['width' => '140'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'class' => ActionColumn::class,
                    'template' => '{view} {update} {delete}',
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
