<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\blog\BlogImage $model */

$this->title = 'Update Blog Image: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Blog Images', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="blog-image-update" style="padding: 20px">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
