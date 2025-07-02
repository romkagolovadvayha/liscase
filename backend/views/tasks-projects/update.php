<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\tasks\TasksProjects $model */

$this->title = 'Update Tasks Projects: ' . $model->title;
$this->params['breadcrumbs'][] = ['label' => 'Tasks Projects', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->title, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="tasks-projects-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
