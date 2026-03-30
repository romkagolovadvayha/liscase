<?php

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use backend\forms\userProfile\BalanceTransferForm;

/* @var $this yii\web\View */
/* @var $balanceTransferForm BalanceTransferForm */

?>

<div class="modal-header">
    <h5 class="modal-title">Перевод на другой аккаунт</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
</div>
<div class="modal-body">
    <p class="user-profile-modal__hint">Списание с лицевого счёта этого пользователя и зачисление получателю. Укажите Steam ID получателя (профиль Steam или блок «Основная информация» на его профиле).</p>
    <?php $form = ActiveForm::begin() ?>
    <?= $form->errorSummary($balanceTransferForm, ['class' => 'alert alert-danger mb-3']) ?>
    <?= $form->field($balanceTransferForm, 'recipientSteamId')->textInput([
        'placeholder' => '76561198…',
        'inputmode' => 'numeric',
        'autocomplete' => 'off',
    ]) ?>
    <?= $form->field($balanceTransferForm, 'amount')->textInput([
        'placeholder' => '0.00',
        'inputmode' => 'decimal',
    ]) ?>
    <?= Html::submitButton('Перевести', ['data-confirm' => 'Подтвердить перевод средств?', 'class' => 'ds-btn ds-btn--primary w-100']) ?>
    <?php ActiveForm::end() ?>
</div>
