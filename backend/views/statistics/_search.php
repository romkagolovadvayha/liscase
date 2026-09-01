<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\statistics\StatisticsSearch $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="statistics-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'steam_id') ?>

    <?= $form->field($model, 'key') ?>

    <?= $form->field($model, 'value') ?>

    <?= $form->field($model, 'server_tag') ?>

    <?php // echo $form->field($model, 'wipe') ?>

    <div class="form-group">
        <?= Html::submitButton('Найти', ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::resetButton('Сбросить', ['class' => 'ds-btn ds-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
