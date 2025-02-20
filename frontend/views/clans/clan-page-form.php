<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\clan\ClanPage $model */
/** @var ActiveForm $form */
?>
<div class="clans-clan-page-form">

    <?php $form = ActiveForm::begin(); ?>

        <?= $form->field($model, 'user_id') ?>
        <?= $form->field($model, 'clan_id') ?>
        <?= $form->field($model, 'sort') ?>
        <?= $form->field($model, 'text') ?>
        <?= $form->field($model, 'created_at') ?>
        <?= $form->field($model, 'title') ?>
    
        <div class="form-group">
            <?= Html::submitButton('Submit', ['class' => 'btn btn-primary']) ?>
        </div>
    <?php ActiveForm::end(); ?>

</div><!-- clans-clan-page-form -->
