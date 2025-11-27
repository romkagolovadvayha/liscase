<?php

use common\models\blog\Blog;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use kartik\grid\GridView;
use yii\helpers\ArrayHelper;

/** @var yii\web\View $this */
/** @var backend\models\blog\BlogSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Блог';
$this->params['breadcrumbs'][] = $this->title;
$cacheData = Yii::$app->cache->get('actionGeneratePosts');
?>
<div class="blog-index-page">
    <div class="content-header">
        <div class="ds-flex ds-flex--between">
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="ds-flex ds-flex--gap-md">
                <?= Html::a('<i class="fas fa-plus"></i> Добавить пост', ['create'], ['class' => 'ds-btn ds-btn--success']) ?>
                <?= Html::a('<i class="fas fa-folder"></i> Категории', ['/blog-category'], ['class' => 'ds-btn ds-btn--primary']) ?>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="ds-card">
            <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            [
                'attribute' => 'id',
                'options'   => ['width' => '50'],
            ],
            'name:ntext',
            [
                'attribute' => 'blog_category_id',
                'options'   => ['width' => '250'],
                'format'    => 'raw',
                'value'     => function (Blog $model) {
                    return Html::a(Html::encode($model->blogCategory->name), 
                        ['/blog-category/view', 'id' => $model->blogCategory->id],
                        ['class' => 'ds-text--primary', 'style' => 'text-decoration: none;']
                    );
                },
            ],
            [
                'attribute' => 'status',
                'options'   => ['width' => '180'],
                'filterType'  => GridView::FILTER_SELECT2,
                'filter'    => ArrayHelper::merge(['' => 'Любой'], Blog::getStatusList()),
                'format'    => 'raw',
                'value'     => function (Blog $model) {
                    $status = ArrayHelper::getValue(Blog::getStatusList(), $model->status);
                    $badgeClass = $model->status == Blog::STATUS_ACTIVE ? 'ds-badge--success' : 'ds-badge--danger';
                    return Html::tag('span', Html::encode($status), ['class' => 'ds-badge ' . $badgeClass]);
                },
            ],
            [
                'attribute' => 'created_at',
                'options'   => ['width' => '200'],
            ],
            [
                'class' => ActionColumn::className(),
                'options'   => ['width' => '120'],
                'template' => '{view} {update} {delete} {publish-vk} {publish-telegram}',
                'buttons' => [
                    'publish-vk' => function ($url, Blog $model, $key) {
                        return Html::a(
                            '<i class="fab fa-vk"></i>',
                            ['publish-to-vk', 'id' => $model->id],
                            [
                                'class' => 'ds-btn ds-btn--warning ds-btn--sm',
                                'title' => 'Опубликовать в ВКонтакте',
                                'data' => [
                                    'confirm' => 'Опубликовать этот пост в группу ВКонтакте?',
                                    'method' => 'post',
                                ],
                            ]
                        );
                    },
                    'publish-telegram' => function ($url, Blog $model, $key) {
                        return Html::a(
                            '<i class="fab fa-telegram"></i>',
                            ['publish-to-telegram', 'id' => $model->id],
                            [
                                'class' => 'ds-btn ds-btn--info ds-btn--sm',
                                'title' => 'Опубликовать в Telegram',
                                'data' => [
                                    'confirm' => 'Опубликовать этот пост в Telegram канал?',
                                    'method' => 'post',
                                ],
                            ]
                        );
                    },
                ],
                'urlCreator' => function ($action, Blog $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>
        </div>
    </div>
</div>
