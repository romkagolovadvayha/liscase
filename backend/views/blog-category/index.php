<?php

use backend\models\blog\BlogCategorySearch;
use common\models\blog\Blog;
use common\models\blog\BlogCategory;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\grid\ActionColumn;

/** @var BlogCategorySearch $searchModel */
/** @var yii\data\ArrayDataProvider $dataProvider */

$this->title = Yii::t('common', 'Категории блога');
$this->params['contentClass'] = 'content-no-padding';
$this->params['searchModel'] = $searchModel;

$headerCellClass = 'px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider bg-[hsl(0_0%_20.4%_/_1)] border-b border-[hsl(0_0%_15.3%_/_1)]';
$bodyCellClass = 'px-4 py-3 text-white border-b border-[hsl(0_0%_15.3%_/_1)]';
?>
<div class="blog-category-index-page w-full">
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
            'rowOptions' => function (BlogCategory $model, $index, $widget, $grid) {
                return ['class' => !empty($model->blog_category_id) ? 'sub-category' : 'category'];
            },
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
                    'contentOptions' => function (BlogCategory $model) use ($bodyCellClass) {
                        $class = $bodyCellClass;
                        if (!empty($model->blog_category_id)) {
                            $class .= ' pl-8';
                        }
                        return ['class' => $class];
                    },
                ],
                [
                    'attribute' => 'description',
                    'format' => 'ntext',
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass . ' max-w-[200px] truncate'],
                ],
                [
                    'attribute' => 'status',
                    'options' => ['width' => '120'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'format' => 'raw',
                    'value' => function (BlogCategory $model) {
                        $statusList = BlogCategory::getStatusList();
                        $status = ArrayHelper::getValue($statusList, $model->status, '');
                        $badgeClass = $model->status == BlogCategory::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
                        return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
                    },
                ],
                [
                    'attribute' => 'gen',
                    'label' => Yii::t('common', 'Генерация постов'),
                    'format' => 'raw',
                    'options' => ['width' => '140'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (BlogCategory $model) {
                        if (empty($model->blog_category_id)) {
                            return '';
                        }
                        $cacheData = Yii::$app->cache->get('actionGenerate_Posts_' . $model->id);
                        if (empty($cacheData)) {
                            return Html::a(Yii::t('common', 'Генерация постов'), ['/blog/generate', 'categoryId' => $model->id], [
                                'class' => 'text-green-400 hover:underline text-xs',
                                'data' => ['confirm' => Yii::t('common', 'Вы уверены?'), 'method' => 'post'],
                            ]);
                        }
                        return Html::tag('span', Yii::t('common', 'Идёт генерация'), ['class' => 'text-gray-500 text-xs']);
                    },
                ],
                [
                    'label' => Yii::t('common', 'Кол-во постов'),
                    'format' => 'raw',
                    'options' => ['width' => '100'],
                    'headerOptions' => ['class' => $headerCellClass],
                    'contentOptions' => ['class' => $bodyCellClass],
                    'value' => function (BlogCategory $model) {
                        $count = Blog::find()->andWhere(['blog_category_id' => $model->id])->count();
                        if (!empty($model->childCategories)) {
                            foreach ($model->childCategories as $category) {
                                $count += Blog::find()->andWhere(['blog_category_id' => $category->id])->count();
                            }
                        }
                        return $count;
                    },
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
