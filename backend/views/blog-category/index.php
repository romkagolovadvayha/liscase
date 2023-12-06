<?php

use common\models\blog\BlogCategory;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\helpers\ArrayHelper;
use kartik\grid\GridView;

/** @var yii\web\View $this */
/** @var backend\models\blog\BlogCategorySearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Категории';
$this->params['breadcrumbs'][] = ['label' => 'Блог', 'url' => ['/blog']];
$this->params['breadcrumbs'][] = $this->title;
$cacheData = Yii::$app->cache->get('actionGenerate');
//Yii::$app->cache->delete('actionGenerateError');
//Yii::$app->cache->delete('actionGenerate_Posts_146');
//Yii::$app->cache->delete('actionGenerate_Posts_148');
?>
<div class="blog-category-index">
    <p>
        <?= Html::a('Добавить категорию', ['create'], ['class' => 'btn btn-success']) ?>

        <?php if (empty($cacheData)): ?>
            <?= Html::a('Генерация категорий', ['generate'], ['class' => 'btn btn-primary', 'data' => [
                'confirm' => 'Вы уверены?',
                'method' => 'post',
            ]]) ?>
        <?php else: ?>
            <?= Html::a('Идет процес генерации', [''], ['class' => 'btn btn-default disabled']) ?>
        <?php endif; ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'rowOptions' => function (BlogCategory $model, $index, $widget, $grid){
            if(!empty($model->blog_category_id)) {
                return ['class' => 'sub-category'];
            } else {
                return ['class' => 'category'];
            }
        },
        'columns' => [
            [
                'attribute' => 'id',
                'options'   => ['width' => '50'],
            ],
            'name',
            'description:ntext',
            [
                'attribute' => 'status',
                'options'   => ['width' => '100'],
                'contentOptions' => ['style' => ['text-align' => 'center']],
                'value'     => function (BlogCategory $model) {
                    return ArrayHelper::getValue(BlogCategory::getStatusList(), $model->status);
                },
            ],
            [
                'attribute' => 'gen',
                'label' => 'Генерация постов',
                'format' => 'raw',
                'options'   => ['width' => '160'],
                'contentOptions' => ['style' => ['text-align' => 'center']],
                'value'     => function (BlogCategory $model) {
                    if (empty($model->blog_category_id)) {
                        return '';
                    }
                    $cacheErrorData = Yii::$app->cache->get('actionGenerateError_Posts_' . $model->id);
                    if (!empty($cacheErrorData)) {
                        Yii::$app->session->addFlash('danger', "Ошибка генерации: " . $cacheErrorData);
                    }

                    $cacheData = Yii::$app->cache->get('actionGenerate_Posts_' . $model->id);
                    if (empty($cacheData)) {
                        return Html::a('Генерация постов', ['/blog/generate?categoryId=' . $model->id], ['class' => 'btn btn-sm btn-success', 'data' => [
                            'confirm' => 'Вы уверены?',
                            'method' => 'post',
                        ]]);
                    } else {
                        return Html::a('Идет генерация', [''], ['class' => 'btn btn-sm btn-default disabled']);
                    }
                 }
            ],
            [
                'attribute' => 'posts',
                'label' => 'Кол-во постов',
                'format' => 'raw',
                'options'   => ['width' => '60'],
                'contentOptions' => ['style' => ['text-align' => 'center']],
                'value'     => function (BlogCategory $model) {
                    $count = \common\models\blog\Blog::find()->andWhere(['blog_category_id' => $model->id])->count();
                    if (!empty($model->childCategories)) {
                        foreach ($model->childCategories as $category) {
                            $count += \common\models\blog\Blog::find()->andWhere(['blog_category_id' => $category->id])->count();
                        }
                    }
                    return $count;
                }
            ],
            [
                'class' => ActionColumn::className(),
                'options'   => ['width' => '60'],
                'urlCreator' => function ($action, BlogCategory $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>


</div>
