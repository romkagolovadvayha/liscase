<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var backend\models\ServersSearch $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="servers-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'name') ?>

    <?= $form->field($model, 'wipe') ?>

    <?= $form->field($model, 'wipe_type') ?>

    <?= $form->field($model, 'next_wipe') ?>

    <?php // echo $form->field($model, 'global_wipe') ?>

    <?php // echo $form->field($model, 'description') ?>

    <?php // echo $form->field($model, 'rules') ?>

    <?php // echo $form->field($model, 'ip') ?>

    <?php // echo $form->field($model, 'port') ?>

    <?php // echo $form->field($model, 'query') ?>

    <?php // echo $form->field($model, 'rcon') ?>

    <?php // echo $form->field($model, 'rcon_password') ?>

    <?php // echo $form->field($model, 'map') ?>

    <?php // echo $form->field($model, 'players') ?>

    <?php // echo $form->field($model, 'joined') ?>

    <?php // echo $form->field($model, 'queued') ?>

    <?php // echo $form->field($model, 'team_limit') ?>

    <?php // echo $form->field($model, 'max') ?>

    <?php // echo $form->field($model, 'status') ?>

    <?php // echo $form->field($model, 'db_host') ?>

    <?php // echo $form->field($model, 'db_name') ?>

    <?php // echo $form->field($model, 'db_user') ?>

    <?php // echo $form->field($model, 'db_password') ?>

    <?php // echo $form->field($model, 'tag') ?>

    <?php // echo $form->field($model, 'stats_payment') ?>

    <?php // echo $form->field($model, 'skindrops') ?>

    <?php // echo $form->field($model, 'wargm_id') ?>

    <?php // echo $form->field($model, 'commands') ?>

    <?php // echo $form->field($model, 'discord_token') ?>

    <div class="form-group">
        <?= Html::submitButton('Найти', ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::resetButton('Сбросить', ['class' => 'ds-btn ds-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
