<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\blog\BlogCategory $model */

$this->title = 'Изменить: ' . $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Блог', 'url' => ['/blog']];
$this->params['breadcrumbs'][] = ['label' => 'Категории', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->name, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="blog-category-update">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
