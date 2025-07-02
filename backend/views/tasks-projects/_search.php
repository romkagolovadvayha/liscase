<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\tasks\TasksProjectsSearch $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="tasks-projects-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'title') ?>

    <?= $form->field($model, 'icon') ?>

    <?= $form->field($model, 'order_index') ?>

    <?= $form->field($model, 'created_at') ?>

    <?php // echo $form->field($model, 'is_visibility_name') ?>

    <?php // echo $form->field($model, 'system_check_code') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
