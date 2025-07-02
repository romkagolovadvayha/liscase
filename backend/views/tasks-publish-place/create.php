<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\tasks\TasksPublishPlace $model */

$this->title = 'Create Tasks Publish Place';
$this->params['breadcrumbs'][] = ['label' => 'Tasks Publish Places', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="tasks-publish-place-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
