<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\invoice\DepositBonus $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="deposit-bonus-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'bonus')->textInput() ?>

    <?= $form->field($model, 'min_amount')->textInput() ?>

    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
