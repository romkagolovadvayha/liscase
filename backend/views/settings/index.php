<?php

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\web\JsExpression;
use backend\forms\settings\SettingsForm;

/** @var SettingsForm $model */
?>

<?php $form = ActiveForm::begin(); ?>

<?= $form->field($model, 'title')->textInput(); ?>
<?= $form->field($model, 'title_short')->textInput(); ?>
<?= $form->field($model, 'subject')->textInput(); ?>
<?= $form->field($model, 'description')->textarea(); ?>

<?=Html::submitButton('Сохранить', ['class' => 'btn btn-primary']);?>
<?php ActiveForm::end(); ?>
