<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\box\Category $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="category-form">
    <?php $form = ActiveForm::begin(); ?>
    <?= $form->field($model, 'name')->textInput() ?>
    <?= $form->field($model, 'tag')->textInput() ?>
    <?= $form->field($model, 'show_main_block')->dropDownList([0 => 'Нет', 1 => 'Да'], []); ?>
    <?= $form->field($model, 'sort')->textInput() ?>
    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
