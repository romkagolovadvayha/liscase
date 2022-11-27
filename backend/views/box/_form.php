<?php

use common\models\box\Box;
use yii\bootstrap5\ActiveForm;
use yii\web\JsExpression;

/** @var Box $model */
$format = <<< JS
function(item) {
    console.log(item);
    return '<img class="kv-icon-image" style="width: 50px" src="' + item.text + '"/>';
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
<?= $form->field($model, 'type')->dropDownList(Box::getTypeList(), []) ?>
<?= $form->field($model, 'preview_file')->fileInput(); ?>
<?= $form->field($model, 'drop')->widget(\kartik\select2\Select2::class, [
    'data'    => \common\models\box\Drop::getList(),
    'options' => [
        'prompt' => '...',
        'multiple' => true,
    ],
    'showToggleAll' => false,
    'pluginOptions' => [
        'templateResult'       => $format,
        'templateSelection' => $format,
        'escapeMarkup' => new JsExpression('function(m){return m}'),
        'allowClear' => true,
    ],
]); ?>
<?= $form->field($model, 'status')->dropDownList(Box::getStatusList(), []) ?>
<?= $this->context->getFormButtons(); ?>

<?php ActiveForm::end(); ?>
