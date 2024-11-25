<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\blog\Blog $model */

$this->title = 'Create Blog';
$this->params['breadcrumbs'][] = ['label' => 'Блог', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="blog-create">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
