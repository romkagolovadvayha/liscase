<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var backend\models\map\MapSearch $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="map-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'mapId') ?>

    <?= $form->field($model, 'link') ?>

    <?= $form->field($model, 'seed') ?>

    <?= $form->field($model, 'size') ?>

    <?php // echo $form->field($model, 'version') ?>

    <?php // echo $form->field($model, 'image_link') ?>

    <?php // echo $form->field($model, 'image_link_icons') ?>

    <?php // echo $form->field($model, 'created_at') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
