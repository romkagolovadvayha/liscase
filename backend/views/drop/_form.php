<?php

use common\models\box\Drop;
use yii\bootstrap5\ActiveForm;

/** @var Drop $model */
?>

<?php $form = ActiveForm::begin(
    [
        'id' => 'box-form',
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

<?= $form->field($model, 'name')->textInput(); ?>
<?= $form->field($model, 'rust_id')->textInput(); ?>
<?= $form->field($model, 'type_id')->dropDownList(\common\models\box\DropType::getTypeList(), []) ?>
<?= $form->field($model, 'preview_file')->fileInput(); ?>
<?= $form->field($model, 'market_status')->dropDownList(Drop::getMarketStatusList(), []) ?>
<?= $form->field($model, 'count')->textInput(); ?>
<?= $form->field($model, 'discount')->textInput(); ?>
<?= $form->field($model, 'command')->textarea(); ?>
<?= $form->field($model, 'blocked_hour')->textInput(); ?>
<?= $form->field($model, 'price')->textInput(); ?>
<?= $form->field($model, 'min_box')->textInput(); ?>
<?= $form->field($model, 'max_box')->textInput(); ?>
<?= $this->context->getFormButtons(); ?>

<?php ActiveForm::end(); ?>
