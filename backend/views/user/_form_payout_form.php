<?php

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use backend\forms\userProfile\PayoutForm;

/* @var $this yii\web\View */
/* @var $payoutForm PayoutForm */

?>

<div class="modal-header">
    <h5 class="modal-title">Вывод с реферальной системы</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
</div>
<div class="modal-body">
    <p class="user-profile-modal__hint">Списание суммы, доступной к выводу по реферальной программе.</p>
    <?php $form = ActiveForm::begin() ?>
    <?= $form->field($payoutForm, 'amount')->textInput(['placeholder' => '0']) ?>
    <?= Html::submitButton('Подтвердить вывод', ['data-confirm' => 'Вы действительно уверены?', 'class' => 'ds-btn ds-btn--primary w-100']) ?>
    <?php ActiveForm::end() ?>
</div>
