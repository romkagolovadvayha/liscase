<?php

use common\models\box\Box;
use yii\bootstrap5\ActiveForm;
use yii\web\JsExpression;

/** @var Box $model */
$format = <<< JS
function(item) {
    try {
        var model = JSON.parse(item.text);
        return '<div class="drop-select-item"><img class="kv-icon-image" src="' + model.image + '" alt=""/><span>' + model.name + '</span></div>';
    } catch {
        return item.text;
    }
}
JS;
$format = new JsExpression($format);
?>
<?php $form = ActiveForm::begin(
    [
        'id' => 'box-form',
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

<?= $form->field($model, 'name')->textInput(); ?>
<?= $form->field($model, 'price')->textInput(); ?>
<div class="ds-select-wrapper">
        <?= $form->field($model, 'type', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList(Box::getTypeList(), ['class' => 'ds-select form-control']) ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>
<?= $form->field($model, 'preview_file')->fileInput(); ?>
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
        <?= $form->field($model, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList(Box::getStatusList(), ['class' => 'ds-select form-control']) ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>
<?= $this->context->getFormButtons(); ?>

<?php ActiveForm::end(); ?>
