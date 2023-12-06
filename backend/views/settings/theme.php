<?php

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use backend\forms\settings\ThemeForm;

/** @var ThemeForm $model */
?>

<?php $form = ActiveForm::begin(); ?>

<?= $form->field($model, 'body_background')->textInput(); ?>
<?= $form->field($model, 'a_color')->textInput(); ?>
<?= $form->field($model, 'header_top_background')->textInput(); ?>
<?= $form->field($model, 'header_top_color')->textInput(); ?>
<?= $form->field($model, 'header_background')->textInput(); ?>
<?= $form->field($model, 'header_color')->textInput(); ?>
<?= $form->field($model, 'logo_background')->textInput(); ?>
<?= $form->field($model, 'logo_color')->textInput(); ?>
<?= $form->field($model, 'footer')->textInput(); ?>
<?= $form->field($model, 'footer_text')->textInput(); ?>
<?= $form->field($model, 'button')->textInput(); ?>
<?= $form->field($model, 'button_text')->textInput(); ?>
<?= $form->field($model, 'blog_item_background')->textInput(); ?>
<?= $form->field($model, 'blog_item_color')->textInput(); ?>
<?= $form->field($model, 'blog_item_data_color')->textInput(); ?>

<?=Html::submitButton('Сохранить', ['class' => 'btn btn-primary']);?>
<?php ActiveForm::end(); ?>
