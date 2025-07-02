<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\tasks\TasksTagsAppointments $model */

$this->title = 'Update Tasks Tags Appointments: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Tasks Tags Appointments', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="tasks-tags-appointments-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
