<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\invoice\Deposit $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="deposit-form-page">
    <div class="ds-card">
        <?php $form = ActiveForm::begin(); ?>

        <?= $form->field($model, 'amount')->textInput(['class' => 'ds-input']) ?>

        <?= $form->field($model, 'payment_id')->textarea(['rows' => 6, 'class' => 'ds-input']) ?>

        <?= $form->field($model, 'status')->dropDownList(
            \common\models\invoice\Deposit::getStatusList(), 
            ['class' => 'ds-input']
        ) ?>

        <?= $form->field($model, 'created_at')->textInput(['class' => 'ds-input']) ?>

        <div class="form-group">
            <?= Html::submitButton('<i class="fas fa-save"></i> Сохранить', ['class' => 'ds-btn ds-btn--success']) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>
