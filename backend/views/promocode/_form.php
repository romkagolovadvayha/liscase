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

<?= $form->field($model, 'code')->textInput(); ?>
<?= $form->field($model, 'amount')->textInput(); ?>
<?= $form->field($model, 'finished_at')->textInput(); ?>
<?= $form->field($model, 'status')->dropDownList(Promocode::getStatusList(), []) ?>
<?= $this->context->getFormButtons(); ?>

<?php ActiveForm::end(); ?>
