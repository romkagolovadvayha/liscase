<?php

use common\models\box\Select;
use yii\bootstrap5\ActiveForm;
use yii\web\JsExpression;

/** @var Select $model */
$format = <<< JS
function(item) {
    try {
        var model = JSON.parse(item.text);
        return '<div class="drop-select-item"><img class="kv-icon-image" src="' + model.image + '"/><span>' + model.name + '</span></div>';
    } catch {
        return item.text;
    }
}
JS;
$format = new JsExpression($format);
?>
<style>
    .select2-results__options {
        display: flex;
        flex-wrap: wrap;
    }
    .drop-select-item {
        padding: 5px;
        background: #f1f1f1;
        border-radius: 5px;
        text-align: center;
        display: flex;
        align-items: center;
        gap: 5px;
        color: #000;
        justify-content: flex-start;
    }
    .drop-select-item img {
        display: block;
        width: 24px;
    }
    .drop-select-item span {
        display: block;
    }
</style>
<?php $form = ActiveForm::begin(
    [
        'id' => 'select-form',
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

<?= $form->field($model, 'name')->textInput(); ?>
<?= $form->field($model, 'description')->textarea(); ?>
<?= $form->field($model, 'preview_file')->fileInput(); ?>
<?= $form->field($model, 'preview_file_open')->fileInput(); ?>
<div class="ds-select-wrapper">
        <?= $form->field($model, 'show_main_block', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList([0 => 'Нет', 1 => 'Да'], ['class' => 'ds-select form-control']) ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>
<?= $form->field($model, 'drop')->widget(\kartik\select2\Select2::class, [
    'data'    => \common\models\box\Drop::getList(),
    'options' => [
        'prompt' => '...',
        'multiple' => true,
    ],
    'showToggleAll' => true,
    'pluginOptions' => [
        'templateResult'       => $format,
        'templateSelection' => $format,
        'escapeMarkup' => new JsExpression('function(m){return m}'),
        'allowClear' => true,
    ],
]); ?>
<div class="ds-select-wrapper">
        <?= $form->field($model, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList(Select::getStatusList(), ['class' => 'ds-select form-control']) ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>
<?= $this->context->getFormButtons(); ?>

<?php ActiveForm::end(); ?>
