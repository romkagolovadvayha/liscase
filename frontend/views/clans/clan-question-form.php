<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\clan\ClanQuestion $model */
/** @var ActiveForm $form */
?>
<div class="clans-clan-question-form">

    <?php $form = ActiveForm::begin(); ?>

        <?= $form->field($model, 'user_id') ?>
        <?= $form->field($model, 'clan_id') ?>
        <?= $form->field($model, 'status') ?>
        <?= $form->field($model, 'created_at') ?>
        <?= $form->field($model, 'description') ?>
        <?= $form->field($model, 'social_youtube') ?>
        <?= $form->field($model, 'social_discord') ?>
        <?= $form->field($model, 'social_vk') ?>
        <?= $form->field($model, 'social_twitch') ?>
    
        <div class="form-group">
            <?= Html::submitButton('Submit', ['class' => 'btn btn-primary']) ?>
        </div>
    <?php ActiveForm::end(); ?>

</div><!-- clans-clan-question-form -->
