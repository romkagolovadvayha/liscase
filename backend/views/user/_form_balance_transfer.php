<?php

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use backend\forms\userProfile\BalanceTransferForm;

/* @var $this yii\web\View */
/* @var $balanceTransferForm BalanceTransferForm */

?>

<div class="modal-header">
    <h5 class="modal-title">Перевод на другой аккаунт</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <p class="text-muted small mb-3">Списание с лицевого счёта этого пользователя и зачисление получателю. Steam ID — из профиля Steam или блока «Основная информация» на профиле получателя.</p>
    <?php $form = ActiveForm::begin() ?>
    <?= $form->errorSummary($balanceTransferForm, ['class' => 'alert alert-danger']) ?>
    <?= $form->field($balanceTransferForm, 'recipientSteamId')->textInput([
        'placeholder' => '76561198…',
        'inputmode' => 'numeric',
        'autocomplete' => 'off',
    ]) ?>
    <?= $form->field($balanceTransferForm, 'amount')->textInput([
        'placeholder' => '0.00',
        'inputmode' => 'decimal',
    ]) ?>
    <?= Html::submitButton('Перевести', ['data-confirm' => 'Подтвердить перевод средств?', 'class' => 'btn btn-primary']) ?>
    <?php ActiveForm::end() ?>
</div>
