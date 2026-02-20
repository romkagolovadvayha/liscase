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
<?= $form->field($model, 'preview_file_open')->fileInput(); ?>
<div class="ds-select-wrapper">
        <?= $form->field($model, 'show_main_block', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList([0 => 'Нет', 1 => 'Да'], ['class' => 'ds-select form-control']) ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>
    <div class="ds-select-wrapper">
        <?= $form->field($model, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList(Sets::getStatusList(), ['class' => 'ds-select form-control']) ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>
<?= $this->context->getFormButtons(); ?>

<?php ActiveForm::end(); ?>
