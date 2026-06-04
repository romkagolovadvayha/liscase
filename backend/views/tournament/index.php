<?php

use backend\models\TournamentSearch;
use common\models\tournament\Tournament;
use kartik\grid\GridView;
use yii\helpers\Html;

/** @var TournamentSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Турниры');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';

$statusLabels = [
    Tournament::STATUS_DRAFT => Yii::t('common', 'Черновик'),
    Tournament::STATUS_PUBLISHED => Yii::t('common', 'Опубликован'),
    Tournament::STATUS_ARCHIVED => Yii::t('common', 'В архиве'),
];
?>

<div class="tournament-index-page w-full">
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
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
            ],
            [
                'attribute' => 'title',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'format' => 'raw',
                'value' => static function (Tournament $model) {
                    return Html::a(Html::encode($model->title), ['view', 'id' => $model->id], ['class' => 'text-white hover:underline']);
                },
            ],
            [
                'attribute' => 'slug',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
            ],
            [
                'attribute' => 'server_id',
                'label' => Yii::t('common', 'Сервер'),
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value' => static fn (Tournament $m) => $m->server ? $m->server->name : '—',
            ],
            [
                'attribute' => 'status',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value' => static fn (Tournament $m) => $statusLabels[$m->status] ?? $m->status,
            ],
            [
                'label' => Yii::t('common', 'Фаза'),
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value' => static fn (Tournament $m) => $m->getPublicPhase(),
            ],
            [
                'attribute' => 'starts_at',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass . ' text-right'],
                'template' => '{view} {update}',
            ],
        ],
    ]) ?>
</div>
