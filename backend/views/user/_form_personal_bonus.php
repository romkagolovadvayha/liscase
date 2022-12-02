<?php

use yii\bootstrap5\Html;
use yii\bootstrap5\ActiveForm;
use backend\forms\userProfile\BonusForm;
use common\models\user\UserBalance;

/* @var $this yii\web\View */
/* @var $bonusForm BonusForm */

?>

<div class="panel panel-info">
    <div class="panel-heading"><h3 class="panel-title">Начислить на лицевой счет</h3></div>
    <div class="panel-body">
        <?php $form = ActiveForm::begin() ?>
        <?= $form->field($bonusForm, 'type_balance')->dropDownList(UserBalance::getTypeList()); ?>
        <?= $form->field($bonusForm, 'amount')->textInput(['placeholder' => 0]) ?>
        <?= Html::submitButton('Начислить', ['data-confirm' => 'Вы действительно хотите начислить бонус?', 'class' => 'btn btn-success']) ?>
        <?php ActiveForm::end() ?>
    </div>
</div>