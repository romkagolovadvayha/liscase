<?php

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use backend\forms\userProfile\PasswordForm;

/* @var $this yii\web\View */
/* @var $passwordForm PasswordForm */

?>

<div class="panel panel-info">
    <div class="panel-heading"><h3 class="panel-title">Смена пароля</h3></div>
    <div class="panel-body">
        <?php $form = \yii\bootstrap5\ActiveForm::begin() ?>
        <?= $form->field($passwordForm, 'password')->passwordInput(['placeholder' => 'Введите новый пароль...']) ?>
        <?= Html::submitButton('Сохранить', ['data-confirm' => 'Вы действительно хотите изменить пароль?', 'class' => 'btn btn-success']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>