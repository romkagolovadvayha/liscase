<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\invoice\PaymentBonuses $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="payment-bonuses-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'amount')->textInput() ?>

    <?= $form->field($model, 'bonus')->textInput() ?>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'ds-btn ds-btn--primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
