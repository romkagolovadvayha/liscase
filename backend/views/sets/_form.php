<?php

use common\models\box\Sets;
use yii\bootstrap5\ActiveForm;
use yii\web\JsExpression;

/** @var Sets $model */
?>

<?php $form = ActiveForm::begin(
    [
        'id' => 'sets-form',
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

<?= $form->field($model, 'name')->textInput(); ?>
<?= $form->field($model, 'discount')->textInput(); ?>
<?= $form->field($model, 'price')->textInput(); ?>
<?= $form->field($model, 'description')->textarea(); ?>
<?= $form->field($model, 'preview_file')->fileInput(); ?>
<?= $form->field($model, 'status')->dropDownList(Sets::getStatusList(), []) ?>
<?= $this->context->getFormButtons(); ?>

<?php ActiveForm::end(); ?>
