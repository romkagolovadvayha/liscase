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
    <?= $form->field($model, 'finished_at')->textInput(); ?>
    <?= $form->field($model, 'status')->dropDownList(Promocode::getStatusList(), []) ?>
</div>
<footer>
    <button type="submit" class="btn">
        <span class="button__text">Сохранить</span>
    </button>
</footer>

<?php ActiveForm::end(); ?>
