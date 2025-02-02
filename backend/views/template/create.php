<?php
use yii\widgets\ActiveForm;
use yii\helpers\Html;
?>

<h1>Create New Template</h1>

<?php $form = ActiveForm::begin(); ?>

<?= $form->field($template, 'name') ?>

<div class="form-group">
    <?= Html::submitButton('Create', ['class' => 'btn btn-success']) ?>
</div>

<?php ActiveForm::end(); ?>
