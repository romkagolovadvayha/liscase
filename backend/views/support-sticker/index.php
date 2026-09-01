<?php

use backend\models\support\SupportStickerSearch;
use common\models\support\SupportSticker;
use backend\components\AccessibleKartikGridView as GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\grid\ActionColumn;

/** @var SupportStickerSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Стикеры поддержки');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>
<div class="support-sticker-index-page w-full">
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
                    'value' => function (SupportSticker $model) {
                        if ($model->type !== SupportSticker::TYPE_IMAGE) {
                            return '<span class="drop-index-preview-placeholder">🎬</span>';
                        }
                        $url = $model->getPublicUrl();
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
                [
                    'attribute' => 'code',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'options' => ['width' => '120'],
                ],
                [
                    'attribute' => 'name',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'attribute' => 'type',
                    'options' => ['width' => '120'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter' => ArrayHelper::merge(['' => 'Любой'], SupportSticker::getTypeList()),
                    'format' => 'raw',
                    'value' => function (SupportSticker $model) {
                        $typeList = SupportSticker::getTypeList();
                        return Html::encode(ArrayHelper::getValue($typeList, $model->type, ''));
                    },
                ],
                [
                    'attribute' => 'status',
                    'options' => ['width' => '120'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter' => ArrayHelper::merge(['' => 'Любой'], SupportSticker::getStatusList()),
                    'format' => 'raw',
                    'value' => function (SupportSticker $model) {
                        $statusList = SupportSticker::getStatusList();
                        $status = ArrayHelper::getValue($statusList, $model->status, '');
                        $badgeClass = $model->status == SupportSticker::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
                        return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
                    },
                ],
                [
                    'attribute' => 'sort',
                    'options' => ['width' => '80'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'class' => \common\components\grid\DateColumn::class,
                    'options' => ['width' => '140'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
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
