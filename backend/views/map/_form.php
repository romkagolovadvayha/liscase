<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\map\Map $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="map-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'mapId')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'link')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'seed')->textInput() ?>

    <?= $form->field($model, 'size')->textInput() ?>

    <?= $form->field($model, 'version')->textInput() ?>

    <?= $form->field($model, 'image_link')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'image_link_icons')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'created_at')->textInput() ?>

    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
