<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\blog\BlogCategory $model */

$this->title = 'Добавить';
$this->params['breadcrumbs'][] = ['label' => 'Блог', 'url' => ['/blog']];
$this->params['breadcrumbs'][] = ['label' => 'Категории', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="blog-category-create">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
