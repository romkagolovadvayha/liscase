<?php

use yii\helpers\Html;
use yii\helpers\Url;
use backend\components\AccessibleKartikGridView as GridView;
use yii\grid\ActionColumn;
use backend\models\TelegramConstructorMessage;

/** @var $this yii\web\View */
/** @var $searchModel backend\models\TelegramConstructorMessageSearch */
/** @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Сообщения для рассылок';
$this->params['contentClass'] = 'content-no-padding';
$this->params['breadcrumbs'][] = ['label' => 'Конструктор рассылок', 'url' => ['/telegram-constructor']];
$this->params['breadcrumbs'][] = $this->title;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>
<div class="tcm-index-page w-full">
    <?= \frontend\widgets\Alert::widget() ?>

    <div class="tcm-index-top p-4 lg:p-6 border-b border-[hsl(0_0%_15.3%_/_1)]">
        <div class="flex flex-wrap gap-2">
            <?= Html::a('<i class="fas fa-plus"></i> Добавить', ['create'], ['class' => 'ds-btn ds-btn--success']) ?>
            <?= Html::a('<i class="fas fa-arrow-left"></i> Конструктор рассылок', ['/telegram-constructor/index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
        </div>
    </div>

    <div class="tcm-index-table-wrap">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
            'tableOptions' => ['class' => 'table-auto w-full text-sm'],
            'options' => ['class' => 'admin-grid-view-dark tcm-grid-view'],
            'layout' => "{items}\n{pager}",
            'filterRowOptions' => ['style' => 'display: none;'],
            'bordered' => false,
            'striped' => false,
            'hover' => true,
            'columns' => [
                [
                    'attribute' => 'id',
                    'options' => ['width' => '70'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'attribute' => 'image_link',
                    'label' => '',
                    'filter' => false,
                    'format' => 'raw',
                    'options' => ['width' => '64'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass . ' tcm-preview-cell'],
                    'value' => function (TelegramConstructorMessage $model) {
                        $url = $model->getPubUrl();
                        if (!$url) {
                            return '<span class="tcm-preview-placeholder">—</span>';
                        }
                        return Html::img($url, [
                            'width' => 48,
                            'height' => 48,
                            'style' => 'object-fit: cover; border-radius: 4px; display: block;',
                            'loading' => 'lazy',
                            'alt' => 'Preview',
                        ]);
                    },
                ],
                [
                    'attribute' => 'title',
                    'format' => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function ($model) {
                        $title = trim((string) $model->title);
                        $label = $title !== '' ? $title : 'Сообщение #' . $model->id;
                        return Html::a(
                            Html::encode($label),
                            ['view', 'id' => $model->id],
                            [
                                'class' => 'text-white hover:underline',
                                'style' => 'text-decoration: none;',
                                'aria-label' => 'Открыть сообщение «' . $label . '»',
                            ]
                        );
                    },
                ],
                [
                    'attribute' => 'created_at',
                    'class' => \common\components\grid\DateColumn::class,
                    'options' => ['width' => '160'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'class' => ActionColumn::class,
                    'template' => '{update} {delete}',
                    'options' => ['width' => '100'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'urlCreator' => function ($action, $model, $key, $index, $column) {
                        return Url::toRoute([$action, 'id' => $model->id]);
                    },
                    'buttons' => [
                        'update' => function ($url, $model) {
                            return Html::a(
                                '<i class="fas fa-pencil-alt"></i>',
                                $url,
                                ['class' => 'ds-btn ds-btn--primary ds-btn--sm', 'title' => 'Редактировать']
                            );
                        },
                        'delete' => function ($url, $model) {
                            return Html::a(
                                '<i class="fas fa-trash"></i>',
                                $url,
                                [
                                    'class' => 'ds-btn ds-btn--danger ds-btn--sm',
                                    'title' => 'Удалить',
                                    'data' => [
                                        'confirm' => 'Удалить это сообщение?',
                                        'method' => 'post',
                                    ],
                                ]
                            );
                        },
                    ],
                ],
            ],
        ]); ?>
    </div>
</div>

<style>
.tcm-grid-view { background: hsl(0 0% 10% / 1) !important; }
.tcm-grid-view .table, .tcm-grid-view table, .tcm-grid-view .kv-grid-table { background: hsl(0 0% 10% / 1) !important; border-collapse: collapse; color: white !important; border: none !important; }
.tcm-grid-view .table thead th, .tcm-grid-view table thead th { background: hsl(0 0% 20.4% / 1) !important; color: hsl(0 0% 70% / 1) !important; border: none !important; border-bottom: 1px solid hsl(0 0% 15.3% / 1) !important; }
.tcm-grid-view .table tbody td, .tcm-grid-view table tbody td { background: transparent !important; color: white !important; border: none !important; border-bottom: 1px solid hsl(0 0% 15.3% / 1) !important; }
.tcm-grid-view .table tbody tr:hover { background: hsl(0 0% 15% / 1) !important; }
.tcm-grid-view .pagination, .tcm-grid-view .kv-panel-pager { background: hsl(0 0% 10% / 1) !important; color: white !important; }
.tcm-grid-view .pagination .page-link { background: hsl(0 0% 20.4% / 1) !important; color: white !important; border-color: hsl(0 0% 15.3% / 1) !important; }
.tcm-preview-cell { vertical-align: middle !important; }
.tcm-preview-placeholder { color: hsl(0 0% 50%); font-size: 0.875rem; }
</style>
