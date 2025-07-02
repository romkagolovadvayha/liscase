<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\tasks\Tasks $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="tasks-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'image')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'short_name')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'tasks_publish_place_id')->textInput() ?>

    <?= $form->field($model, 'tasks_projects_id')->textInput() ?>

    <?= $form->field($model, 'date_start')->textInput() ?>

    <?= $form->field($model, 'date_end')->textInput() ?>

    <?= $form->field($model, 'description')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'amount')->textInput() ?>

    <?= $form->field($model, 'amount_icon')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'additional_text')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'url_text')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'url_link')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'button_text')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'button_url')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'reward_amount_signature')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'additional_explanation')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'additional_url_text')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'additional_url_link')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'is_email_field')->textInput() ?>

    <?= $form->field($model, 'is_check_method_auto')->textInput() ?>

    <?= $form->field($model, 'is_permanent')->textInput() ?>

    <?= $form->field($model, 'is_publish')->textInput() ?>

    <?= $form->field($model, 'order_index')->textInput() ?>

    <?= $form->field($model, 'system_check_code')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'lk_lang')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'video_link')->textInput(['maxlength' => true]) ?>

    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
