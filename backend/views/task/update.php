<?php

use common\models\tasks\Task;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var Task $model */
?>

<?php $form = ActiveForm::begin(
    [
        'id' => 'box-form',
        'options' => ['enctype' => 'multipart/form-data']
    ]); ?>

<div class="row">
    <div class="col">
        <?= $form->field($model, 'description')->textInput(); ?>
    </div>
    <div class="col">
        <?= $form->field($model, 'amount')->textInput(); ?>
    </div>
</div>
<div class="row">
    <div class="col">
        <?= $form->field($model, 'drop_id')->textInput(); ?>
    </div>
    <div class="col">
        <?= $form->field($model, 'count')->textInput(); ?>
    </div>
</div>

<?= $form->field($model, 'drop_id_image')->textInput(); ?>
<?= $form->field($model, 'stat_attribute')->textInput(); ?>

<?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>

<?php ActiveForm::end(); ?>
