<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use common\models\blog\BlogCategory;
use yii\helpers\ArrayHelper;

/** @var yii\web\View $this */
/** @var common\models\blog\BlogCategory $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Блог', 'url' => ['/blog']];
$this->params['breadcrumbs'][] = ['label' => 'Категории', 'url' => ['index']];
if (!empty($model->parentCategory)) {
    $this->params['breadcrumbs'][] = ['label' => $model->parentCategory->name, 'url' => ['/blog-category/view', 'id' => $model->parentCategory->id]];
}
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="blog-category-view">
    <p>
        <?= Html::a('Update', ['Изменить', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Delete', ['Удалить', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Are you sure you want to delete this item?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'name',
            [
                'attribute' => 'link_name',
                'label' => 'Ссылка на пост',
                'format' => 'raw',
                'value'     => function (BlogCategory $model) {
                    $link = Yii::$app->params['baseUrl'] . $model->getUrl();
                    return Html::a($link, $link, ['target' => '_blank']);
                },
            ],
            [
                'attribute' => 'blog_category_id',
                'format'    => 'raw',
                'value'     => function (BlogCategory $model) {
                    if (empty($model->parentCategory)) {
                        return 'Нет';
                    }
                    return Html::a($model->parentCategory->name, ['/blog-category/view', 'id' => $model->parentCategory->id]);
                },
            ],
            'description:ntext',
            'keywords:ntext',
            'status',
            'created_at',
        ],
    ]) ?>

</div>
