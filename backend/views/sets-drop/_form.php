<?php

use common\models\box\SetsDrop;
use yii\bootstrap5\ActiveForm;
use yii\web\JsExpression;

/** @var SetsDrop $model */
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
        'id' => 'sets-drops-form',
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

<?= $form->field($model, 'drop_id')->widget(\kartik\select2\Select2::class, [
    'data'    => \common\models\box\Drop::getList(),
    'options' => [
        'prompt' => '...',
        'multiple' => false,
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
        <?= $form->field($model, 'sets_id', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList(\common\models\box\Sets::getList(), ['class' => 'ds-select form-control']) ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>
<?= $form->field($model, 'count')->textInput() ?>
<?= $this->context->getFormButtons(); ?>

<?php ActiveForm::end(); ?>
