<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\blog\BlogImage $model */

$this->title = 'Create Blog Image';
$this->params['breadcrumbs'][] = ['label' => 'Blog Images', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="blog-image-create" style="padding: 20px">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>
