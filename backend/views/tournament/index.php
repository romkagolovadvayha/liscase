<?php

use backend\models\TournamentSearch;
use common\models\tournament\Tournament;
use backend\components\AccessibleKartikGridView as GridView;
use yii\grid\ActionColumn;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var TournamentSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Турниры');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';

$statusBadgeClass = [
    Tournament::STATUS_DRAFT => 'bg-gray-600/40 text-gray-300',
    Tournament::STATUS_PUBLISHED => 'bg-green-600/25 text-green-300',
    Tournament::STATUS_ARCHIVED => 'bg-amber-600/25 text-amber-200',
];

$phaseBadgeClass = [
    Tournament::PHASE_UPCOMING => 'bg-purple-600/25 text-purple-200',
    Tournament::PHASE_ACTIVE => 'bg-green-600/25 text-green-300',
    Tournament::PHASE_PAST => 'bg-gray-600/40 text-gray-400',
];

$formatShort = static function (?string $dt): string {
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date('d.m.Y H:i', $ts) : Html::encode($dt);
};
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
                'options' => ['width' => '64'],
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
            ],
            [
                'attribute' => 'title',
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'format' => 'raw',
                'value' => static function (Tournament $model) {
                    $title = Html::a(Html::encode($model->title), ['view', 'id' => $model->id], [
                        'class' => 'text-white hover:underline font-medium',
                    ]);
                    $slug = Html::tag('span', Html::encode($model->slug), ['class' => 'block text-xs text-gray-500 mt-0.5']);
                    return $title . $slug;
                },
            ],
            [
                'attribute' => 'server_id',
                'label' => Yii::t('common', 'Сервер'),
                'options' => ['width' => '120'],
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'value' => static fn (Tournament $m) => $m->server ? Html::encode($m->server->tag ?: $m->server->name) : '—',
            ],
            [
                'attribute' => 'status',
                'options' => ['width' => '110'],
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'format' => 'raw',
                'value' => static function (Tournament $m) use ($statusBadgeClass) {
                    $cls = $statusBadgeClass[$m->status] ?? 'bg-gray-600/40 text-gray-300';
                    return Html::tag(
                        'span',
                        Html::encode($m->getStatusLabel()),
                        ['class' => 'inline-block px-2 py-0.5 rounded text-xs font-medium ' . $cls]
                    );
                },
            ],
            [
                'label' => Yii::t('common', 'Фаза'),
                'options' => ['width' => '100'],
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
                'format' => 'raw',
                'value' => static function (Tournament $m) use ($phaseBadgeClass) {
                    $phase = $m->getPublicPhase();
                    $cls = $phaseBadgeClass[$phase] ?? 'bg-gray-600/40 text-gray-300';
                    return Html::tag(
                        'span',
                        Html::encode($m->getPhaseLabel()),
                        ['class' => 'inline-block px-2 py-0.5 rounded text-xs font-medium ' . $cls]
                    );
                },
            ],
            [
                'label' => Yii::t('common', 'Период'),
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass . ' text-xs text-gray-300 whitespace-nowrap'],
                'value' => static function (Tournament $m) use ($formatShort) {
                    return $formatShort($m->starts_at) . ' — ' . $formatShort($m->ends_at);
                },
            ],
            [
                'label' => Yii::t('common', 'Кланы'),
                'options' => ['width' => '72'],
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass . ' text-center'],
                'value' => static function (Tournament $m) {
                    $n = $m->getRegisteredClansCount();
                    return $m->max_clans ? $n . '/' . (int)$m->max_clans : (string)$n;
                },
            ],
            [
                'attribute' => 'sort',
                'options' => ['width' => '56'],
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass . ' text-center text-gray-400'],
            ],
            [
                'class' => ActionColumn::class,
                'template' => '{view} {update}',
                'options' => ['width' => '72'],
                'urlCreator' => static fn ($action, Tournament $model) => Url::toRoute([$action, 'id' => $model->id]),
                'headerOptions' => ['class' => $headerCellClass],
                'contentOptions' => ['class' => $bodyCellClass],
            ],
        ],
    ]) ?>
</div>
