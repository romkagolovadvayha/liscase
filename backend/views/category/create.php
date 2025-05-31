<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\box\Category $model */

$this->title = 'Добавить';
$this->params['breadcrumbs'][] = ['label' => 'Категории', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="category-create">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
