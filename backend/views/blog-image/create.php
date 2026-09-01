<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\blog\BlogImage $model */

$this->title = 'Добавить изображение блога';
$this->params['breadcrumbs'][] = ['label' => 'Изображения блога', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="blog-image-create">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
</div>
