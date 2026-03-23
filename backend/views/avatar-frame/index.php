<?php

use backend\models\avatar\AvatarFrameSearch;
use common\models\avatar\AvatarFrame;
use kartik\grid\GridView;
use yii\grid\ActionColumn;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var AvatarFrameSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Рамки аватаров');
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
                    'attribute' => 'image_key',
                    'format' => 'raw',
                    'label' => '',
                    'options' => ['width' => '70'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass . ' drop-index-preview-cell'],
                    'value' => static function (AvatarFrame $model): string {
                        $url = $model->getImageUrl();
                        if (!$url) {
                            return '<span class="drop-index-preview-placeholder">—</span>';
                        }
                        return Html::tag('div', Html::img($url, [
                            'width' => 48,
                            'height' => 48,
                            'loading' => 'lazy',
                            'alt' => 'Avatar frame',
                            'class' => 'drop-index-preview-img',
                        ]), ['class' => 'drop-index-preview']);
                    },
                ],
                [
                    'attribute' => 'is_active',
                    'options' => ['width' => '130'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter' => ArrayHelper::merge(['' => 'Любой'], [1 => 'Активна', 0 => 'Отключена']),
                    'format' => 'raw',
                    'value' => static function (AvatarFrame $model): string {
                        return $model->is_active
                            ? '<span class="ds-badge ds-badge--success">Активна</span>'
                            : '<span class="ds-badge ds-badge--danger">Отключена</span>';
                    },
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
                    'urlCreator' => static function ($action, $model) {
                        return Url::toRoute([$action, 'id' => $model->id]);
                    },
                ],
            ],
        ]); ?>
    </div>
</div>

