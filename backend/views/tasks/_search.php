<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\tasks\TasksSearch $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="tasks-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'image') ?>

    <?= $form->field($model, 'name') ?>

    <?= $form->field($model, 'short_name') ?>

    <?= $form->field($model, 'tasks_publish_place_id') ?>

    <?php // echo $form->field($model, 'tasks_projects_id') ?>

    <?php // echo $form->field($model, 'date_start') ?>

    <?php // echo $form->field($model, 'date_end') ?>

    <?php // echo $form->field($model, 'description') ?>

    <?php // echo $form->field($model, 'amount') ?>

    <?php // echo $form->field($model, 'amount_icon') ?>

    <?php // echo $form->field($model, 'additional_text') ?>

    <?php // echo $form->field($model, 'url_text') ?>

    <?php // echo $form->field($model, 'url_link') ?>

    <?php // echo $form->field($model, 'button_text') ?>

    <?php // echo $form->field($model, 'button_url') ?>

    <?php // echo $form->field($model, 'reward_amount_signature') ?>

    <?php // echo $form->field($model, 'additional_explanation') ?>

    <?php // echo $form->field($model, 'additional_url_text') ?>

    <?php // echo $form->field($model, 'additional_url_link') ?>

    <?php // echo $form->field($model, 'is_email_field') ?>

    <?php // echo $form->field($model, 'is_check_method_auto') ?>

    <?php // echo $form->field($model, 'is_permanent') ?>

    <?php // echo $form->field($model, 'is_publish') ?>

    <?php // echo $form->field($model, 'order_index') ?>

    <?php // echo $form->field($model, 'system_check_code') ?>

    <?php // echo $form->field($model, 'created_at') ?>

    <?php // echo $form->field($model, 'promotion_id') ?>

    <?php // echo $form->field($model, 'is_archive') ?>

    <?php // echo $form->field($model, 'lk_lang') ?>

    <?php // echo $form->field($model, 'video_link') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
