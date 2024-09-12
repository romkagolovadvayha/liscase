<?php

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use backend\forms\userProfile\PayoutForm;
use common\models\user\UserBalance;

/* @var $this yii\web\View */
/* @var $payoutForm PayoutForm */

?>

<div class="panel panel-info">
    <div class="panel-heading"><h3 class="panel-title">Снять средства доступные к выводу с реф. системы</h3></div>
    <div class="panel-body">
        <?php $form = ActiveForm::begin() ?>
        <?= $form->field($payoutForm, 'amount')->textInput(['placeholder' => 0]) ?>
        <?= Html::submitButton('Начислить', ['data-confirm' => 'Вы действительно уверены?', 'class' => 'btn btn-success']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>