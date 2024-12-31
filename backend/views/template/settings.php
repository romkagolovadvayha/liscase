<?php
use yii\widgets\ActiveForm;
use yii\helpers\Html;
?>

<h1>Edit Template: <?= $template->name ?></h1>

<?php $form = ActiveForm::begin(); ?>

<?= $form->field($template, 'name') ?>

<div class="form-group">
    <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
</div>

<?php ActiveForm::end(); ?>
