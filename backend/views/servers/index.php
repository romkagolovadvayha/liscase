<?php

use common\models\servers\Servers;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use kartik\grid\GridView;
use yii\helpers\ArrayHelper;
use yii\widgets\ListView;

/** @var yii\web\View $this */
/** @var backend\models\ServersSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Сервера';
$this->params['breadcrumbs'][] = $this->title;
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;
?>

<div class="servers-index-page w-full">
    <!-- Десктоп: таблица -->
    <div class="servers-index-desktop">
    <div class="w-full">
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
            'tableOptions' => [
                'class' => 'table-auto w-full text-sm servers-table-dark',
            ],
            'options' => [
                'class' => 'servers-grid-view',
            ],
            'layout' => "{items}\n{pager}",
            'filterRowOptions' => ['style' => 'display: none;'], // Скрываем фильтры в таблице
            'bordered' => false,
            'striped' => false,
            'hover' => true,
            'columns' => [
                [
                    'attribute' => 'id',
                    'format' => 'raw',
                    'options' => ['width' => '60'],
                    'headerOptions' => ['class' => 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]'],
                    'contentOptions' => ['class' => 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]'],
                ],
                [
                    'attribute' => 'name',
                    'format' => 'ntext',
                    'headerOptions' => ['class' => 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]'],
                    'contentOptions' => ['class' => 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]'],
                ],
                [
                    'attribute' => 'wipe',
                    'options' => ['width' => '200'],
                    'value' => function (Servers $model) {
                        return $model->wipe;
                    },
                    'headerOptions' => ['class' => 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]'],
                    'contentOptions' => ['class' => 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]'],
                ],
                [
                    'attribute' => 'next_wipe',
                    'options' => ['width' => '200'],
                    'value' => function (Servers $model) {
                        return $model->next_wipe;
                    },
                    'headerOptions' => ['class' => 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]'],
                    'contentOptions' => ['class' => 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]'],
                ],
                [
                    'attribute' => 'global_wipe',
                    'options' => ['width' => '200'],
                    'value' => function (Servers $model) {
                        return $model->global_wipe;
                    },
                    'headerOptions' => ['class' => 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]'],
                    'contentOptions' => ['class' => 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]'],
                ],
                [
                    'attribute' => 'updated_at',
                    'options' => ['width' => '200'],
                    'value' => function (Servers $model) {
                        if ($model->status != Servers::STATUS_ACTIVE) {
                            return '';
                        }
                        return time() - strtotime($model->updated_at) . " сек. назад";
                    },
                    'headerOptions' => ['class' => 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]'],
                    'contentOptions' => ['class' => 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]'],
                ],
                [
                    'attribute' => 'status',
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter' => ArrayHelper::merge(['' => 'Все'], Servers::getStatusList()),
                    'options' => ['width' => '100'],
                    'format' => 'raw',
                    'value' => function (Servers $model) {
                        $status = ArrayHelper::getValue(Servers::getStatusList(), $model->status);
                        $badgeClass = $model->status == Servers::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
                        return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
                    },
                    'headerOptions' => ['class' => 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]'],
                    'contentOptions' => ['class' => 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]'],
                ],
                [
                    'class' => ActionColumn::className(),
                    'options' => ['width' => '40'],
                    'template' => '{update}',
                    'urlCreator' => function ($action, Servers $model, $key, $index, $column) {
                        return Url::toRoute([$action, 'id' => $model->id]);
                    },
                    'headerOptions' => ['class' => 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]'],
                    'contentOptions' => ['class' => 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]'],
                ],
            ],
        ]); ?>
    </div>
    </div>

    <!-- Мобилка: карточки серверов -->
    <div class="servers-index-mobile">
        <?= ListView::widget([
            'dataProvider' => $dataProvider,
            'itemView' => '_server_card',
            'layout' => "{items}\n<div class=\"servers-index-mobile-pager\">{pager}</div>",
            'itemOptions' => ['class' => 'servers-index-card-wrap', 'tag' => 'div'],
            'options' => ['class' => 'servers-index-cards', 'tag' => 'div'],
        ]) ?>
    </div>
</div>

<style>
/* Убираем отступы для таблицы */
.content-no-padding {
    padding: 0 !important;
}

/* Стили для таблицы GridView - темная тема */
.servers-grid-view {
    background: hsl(0 0% 10% / 1) !important;
}

/* Все возможные варианты таблиц */
.servers-grid-view .table,
.servers-grid-view table,
.servers-grid-view .kv-grid-table,
.servers-grid-view .servers-table-dark,
.servers-grid-view .table-striped,
.servers-grid-view .table-bordered,
.servers-grid-view .table-hover,
.servers-grid-view .table-sm {
    background: hsl(0 0% 10% / 1) !important;
    border-collapse: collapse;
    width: 100%;
    color: white !important;
    border: none !important;
    border-spacing: 0;
}

/* Заголовки таблицы */
.servers-grid-view .table thead,
.servers-grid-view table thead,
.servers-grid-view .kv-grid-table thead {
    background: hsl(0 0% 20.4% / 1) !important;
}

.servers-grid-view .table thead th,
.servers-grid-view table thead th,
.servers-grid-view .kv-grid-table thead th,
.servers-grid-view .table thead td,
.servers-grid-view table thead td {
    background: hsl(0 0% 20.4% / 1) !important;
    color: hsl(0 0% 70% / 1) !important;
    border-top: none !important;
    border-left: none !important;
    border-right: none !important;
    border-bottom: 1px solid hsl(0 0% 15.3% / 1) !important;
    font-weight: 600 !important;
}

/* Тело таблицы */
.servers-grid-view .table tbody,
.servers-grid-view table tbody,
.servers-grid-view .kv-grid-table tbody {
    background: hsl(0 0% 10% / 1) !important;
}

.servers-grid-view .table tbody tr,
.servers-grid-view table tbody tr,
.servers-grid-view .kv-grid-table tbody tr {
    background: hsl(0 0% 10% / 1) !important;
    color: white !important;
    border-top: none !important;
    border-left: none !important;
    border-right: none !important;
}

.servers-grid-view .table tbody tr:hover,
.servers-grid-view table tbody tr:hover,
.servers-grid-view .kv-grid-table tbody tr:hover,
.servers-grid-view .table-hover tbody tr:hover {
    background: hsl(0 0% 15% / 1) !important;
}

.servers-grid-view .table tbody td,
.servers-grid-view table tbody td,
.servers-grid-view .kv-grid-table tbody td {
    background: transparent !important;
    color: white !important;
    border-top: none !important;
    border-left: none !important;
    border-right: none !important;
    border-bottom: 1px solid hsl(0 0% 15.3% / 1) !important;
}

.servers-grid-view .table tbody tr:last-child td,
.servers-grid-view table tbody tr:last-child td,
.servers-grid-view .kv-grid-table tbody tr:last-child td {
    border-bottom: none;
}

/* Убираем полосы у striped таблиц */
.servers-grid-view .table-striped tbody tr:nth-of-type(odd) {
    background: hsl(0 0% 10% / 1) !important;
}

.servers-grid-view .table-striped tbody tr:nth-of-type(even) {
    background: hsl(0 0% 12% / 1) !important;
}

/* Стили для пагинации */
.servers-grid-view .pagination,
.servers-grid-view .kv-panel-pager {
    background: hsl(0 0% 10% / 1) !important;
    color: white !important;
}

.servers-grid-view .pagination .page-link {
    background: hsl(0 0% 20.4% / 1) !important;
    color: white !important;
    border-color: hsl(0 0% 15.3% / 1) !important;
}

.servers-grid-view .pagination .page-link:hover {
    background: hsl(0 0% 25% / 1) !important;
}

.servers-grid-view .pagination .page-item.active .page-link {
    background: hsl(200 70% 50% / 1) !important;
    border-color: hsl(200 70% 50% / 1) !important;
}

/* Стили для фильтров в таблице (если они все еще отображаются) */
.servers-grid-view .filters-row input,
.servers-grid-view .filters-row select {
    background: hsl(0 0% 15% / 1) !important;
    border: 1px solid hsl(0 0% 25% / 1) !important;
    color: white !important;
    padding: 0.5rem;
    border-radius: 0.25rem;
    width: 100%;
}

.servers-grid-view .filters-row input:focus,
.servers-grid-view .filters-row select:focus {
    outline: none;
    border-color: hsl(200 70% 50% / 1) !important;
}

/* Мобилка: карточки вместо таблицы */
.servers-index-mobile {
    display: none;
}
@media (max-width: 991px) {
    .servers-index-desktop {
        display: none !important;
    }
    .servers-index-mobile {
        display: block;
        padding: 12px;
    }
}

.servers-index-cards {
    list-style: none;
    margin: 0;
    padding: 0;
}
.servers-index-card-wrap {
    margin-bottom: 12px;
}
.servers-index-card {
    padding: 14px;
    background: hsl(0 0% 15% / 1);
    border-radius: 10px;
    border: 1px solid hsl(0 0% 20% / 1);
    box-sizing: border-box;
}
.servers-index-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 6px;
}
.servers-index-card__name {
    font-weight: 600;
    color: #fff;
    font-size: 1rem;
}
.servers-index-card__meta {
    font-size: 12px;
    color: hsl(0 0% 58%);
    margin-bottom: 10px;
}
.servers-index-card__id,
.servers-index-card__tag {
    margin-right: 10px;
}
.servers-index-card__wipes {
    font-size: 12px;
    color: hsl(0 0% 75%);
    margin-bottom: 8px;
}
.servers-index-card__row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 4px 0;
}
.servers-index-card__label {
    color: hsl(0 0% 55%);
}
.servers-index-card__value {
    color: hsl(0 0% 85%);
}
.servers-index-card__updated {
    font-size: 11px;
    color: hsl(0 0% 50%);
    margin-bottom: 10px;
}
.servers-index-card__action .ds-btn {
    min-height: 44px;
    padding: 10px 14px;
}

.servers-index-mobile-pager {
    margin-top: 16px;
    padding: 12px 0;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
}
.servers-index-mobile-pager .pagination {
    margin: 0;
}
.servers-index-mobile-pager .page-link {
    min-width: 44px;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: hsl(0 0% 20% / 1) !important;
    color: #fff !important;
    border-color: hsl(0 0% 15% / 1) !important;
}
.servers-index-mobile-pager .page-item.active .page-link {
    background: hsl(200 70% 50% / 1) !important;
    border-color: hsl(200 70% 50% / 1) !important;
}
</style>
