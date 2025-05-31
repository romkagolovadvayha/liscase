<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\statistics\Statistics $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="statistics-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'steam_id')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'key')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'value')->textInput() ?>

    <?= $form->field($model, 'server_tag')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'wipe')->textInput(['maxlength' => true]) ?>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
