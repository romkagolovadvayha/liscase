<?php

use common\models\promocode\Promocode;
use yii\bootstrap5\ActiveForm;

/** @var Promocode $model */
?>

<?php $form = ActiveForm::begin(
    [
        'id' => 'box-form',
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

<div class="modal-content-body">
    <?= $form->field($model, 'code')->textInput(); ?>
    <?= $form->field($model, 'amount')->textInput(); ?>
    <?= $form->field($model, 'finished_at')->widget(\kartik\widgets\DateTimePicker::class, [
        'options'       => ['placeholder' => 'Выберите время ...'],
        'convertFormat' => true,
        'removeButton'  => false,
        'pluginOptions' => [
            'format'         => 'yyyy-MM-dd hh:mm:00',
            'startDate'      => '2022-07-01 00',
            'todayHighlight' => true,
            'autoclose'      => true,
            'minuteStep'     => 15,
        ],
    ]); ?>
    <div class="ds-select-wrapper">
        <?= $form->field($model, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList(Promocode::getStatusList(), ['class' => 'ds-select form-control']) ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>
    <button type="submit" class="btn btn-primary">
        <span class="button__text">Сохранить</span>
    </button>
</div>

<?php ActiveForm::end(); ?>
