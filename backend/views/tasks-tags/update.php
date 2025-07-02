<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\tasks\TasksTags $model */

$this->title = 'Update Tasks Tags: ' . $model->title;
$this->params['breadcrumbs'][] = ['label' => 'Tasks Tags', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->title, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="tasks-tags-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
