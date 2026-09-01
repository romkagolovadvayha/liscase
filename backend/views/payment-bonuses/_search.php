<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var backend\models\invoice\PaymentBonusesSearch $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="payment-bonuses-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'amount') ?>

    <?= $form->field($model, 'bonus') ?>

    <?= $form->field($model, 'created_at') ?>

    <div class="form-group">
        <?= Html::submitButton('Найти', ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::resetButton('Сбросить', ['class' => 'ds-btn ds-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
