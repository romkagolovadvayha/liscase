<?php

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use backend\forms\userProfile\BonusForm;
use common\models\user\UserBalance;

/* @var $this yii\web\View */
/* @var $bonusForm BonusForm */

?>

<div class="modal-header">
    <h5 class="modal-title">Начислить на лицевой счет</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <?php $form = ActiveForm::begin() ?>
    <?= $form->field($bonusForm, 'type_balance')->dropDownList(UserBalance::getTypeList()); ?>
    <?= $form->field($bonusForm, 'amount')->textInput(['placeholder' => 0]) ?>
    <?= Html::submitButton('Начислить', ['data-confirm' => 'Вы действительно хотите начислить бонус?', 'class' => 'btn btn-success']) ?>
    <?php ActiveForm::end() ?>
</div>