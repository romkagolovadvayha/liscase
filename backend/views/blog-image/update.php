<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\blog\BlogImage $model */

$this->title = 'Изменить изображение блога №' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Изображения блога', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Изменение';
?>
<div class="blog-image-update">
    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
