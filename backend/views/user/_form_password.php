<?php

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use backend\forms\userProfile\PasswordForm;

/* @var $this yii\web\View */
/* @var $passwordForm PasswordForm */

?>

<div class="modal-header">
    <h5 class="modal-title">Смена пароля</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <?php $form = \yii\bootstrap5\ActiveForm::begin() ?>
    <?= $form->field($passwordForm, 'password')->passwordInput(['placeholder' => 'Введите новый пароль...']) ?>
    <?= Html::submitButton('Сохранить', ['data-confirm' => 'Вы действительно хотите изменить пароль?', 'class' => 'btn btn-success']) ?>
    <?php ActiveForm::end() ?>
</div>