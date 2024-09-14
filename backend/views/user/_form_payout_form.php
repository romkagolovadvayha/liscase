<?php

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use backend\forms\userProfile\PayoutForm;
use common\models\user\UserBalance;

/* @var $this yii\web\View */
/* @var $payoutForm PayoutForm */

?>

<div class="modal-header">
    <h5 class="modal-title">Снять средства доступные к выводу с реф. системы</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <?php $form = ActiveForm::begin() ?>
    <?= $form->field($payoutForm, 'amount')->textInput(['placeholder' => 0]) ?>
    <?= Html::submitButton('Начислить', ['data-confirm' => 'Вы действительно уверены?', 'class' => 'btn btn-success']) ?>
    <?php ActiveForm::end() ?>
</div>