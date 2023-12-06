<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\blog\Blog $model */

$this->title = 'Create Blog';
$this->params['breadcrumbs'][] = ['label' => 'Блог', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="blog-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
