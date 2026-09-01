<?php

use backend\models\blog\BlogSearch;
use common\models\blog\Blog;
use backend\components\AccessibleKartikGridView as GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\grid\ActionColumn;

/** @var BlogSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('common', 'Блог');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>
<div class="blog-index-page w-full">
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
                    'value' => function (Blog $model) {
                        $firstImage = $model->getBlogImages()->one();
                        if (!$firstImage || !$firstImage->link) {
                            return '<span class="drop-index-preview-placeholder">—</span>';
                        }
                        $url = $firstImage->getPublicUrl();
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
                    'attribute' => 'name',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                ],
                [
                    'attribute' => 'blog_category_id',
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter' => ArrayHelper::merge(['' => 'Все'], \common\models\blog\BlogCategory::getChildsCategories()),
                    'options' => ['width' => '180'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'format' => 'raw',
                    'value' => function (Blog $model) {
                        if (!$model->blogCategory) {
                            return null;
                        }
                        return Html::a(Html::encode($model->blogCategory->name),
                            ['/blog-category/view', 'id' => $model->blogCategory->id],
                            ['class' => 'text-blue-400 hover:underline']);
                    },
                ],
                [
                    'attribute' => 'status',
                    'options' => ['width' => '120'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'filterType' => GridView::FILTER_SELECT2,
                    'filter' => ArrayHelper::merge(['' => 'Любой'], Blog::getStatusList()),
                    'format' => 'raw',
                    'value' => function (Blog $model) {
                        $status = ArrayHelper::getValue(Blog::getStatusList(), $model->status, '');
                        $badgeClass = $model->status == Blog::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
                        return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
                    },
                ],
                [
                    'attribute' => 'update_at',
                    'label' => Yii::t('common', 'Дата обновления'),
                    'options' => ['width' => '160'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
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
