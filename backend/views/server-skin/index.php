<?php

use common\models\serverskin\ServerSkin;
use backend\models\serverskin\ServerSkinSearch;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\grid\ActionColumn;

/** @var ServerSkinSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Свои скины');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>
<div class="server-skin-index-page w-full">
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
                    'contentOptions' => ['class' => $bodyCellClass . ' server-skin-index-preview-cell'],
                    'value' => function (ServerSkin $model) {
                        $url = null;
                        if (!empty($model->image)) {
                            try {
                                $url = $model->getImagePubUrl();
                            } catch (\Throwable $e) {
                            }
                        }
                        if (!$url) {
                            return '<span class="server-skin-index-preview-placeholder">—</span>';
                        }
                        return Html::tag('div', Html::img($url, [
                            'width' => 48,
                            'height' => 48,
                            'loading' => 'lazy',
                            'alt' => Html::encode($model->name ?? ''),
                            'class' => 'object-cover rounded',
                            'style' => 'width: 48px; height: 48px; object-fit: cover;',
                        ]), ['class' => 'server-skin-index-preview']);
                    },
                ],
                [
                    'attribute' => 'name',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'attribute' => 'user_id',
                    'options' => ['width' => '150'],
                    'format' => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (ServerSkin $model) {
                        if (!$model->user) {
                            return '—';
                        }
                        return Html::a(Html::encode($model->user->username), ['/user/profile', 'userId' => $model->user->id], ['class' => 'text-blue-400 hover:underline']);
                    },
                ],
                [
                    'attribute' => 'skin_id',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'attribute' => 'status',
                    'options' => ['width' => '140'],
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter' => ArrayHelper::merge(['' => Yii::t('common', 'Любой')], ServerSkin::getStatusList()),
                    'format' => 'raw',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (ServerSkin $model) {
                        $status = ArrayHelper::getValue(ServerSkin::getStatusList(), $model->status, '');
                        $badgeClass = $model->status == ServerSkin::STATUS_ACTIVE ? 'ds-badge--success' : ($model->status == ServerSkin::STATUS_WAIT ? 'ds-badge--warning' : 'ds-badge--danger');
                        return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
                    },
                ],
                [
                    'attribute' => 'created_at',
                    'label' => Yii::t('common', 'Дата создания'),
                    'options' => ['width' => '160'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'format' => ['date', 'php:Y-m-d H:i'],
                ],
                [
                    'class' => ActionColumn::class,
                    'template' => '{view} {update} {delete}',
                    'options' => ['width' => '120'],
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
