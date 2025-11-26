<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var common\models\map\MapList $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="map-list-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'hash')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'url')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'size')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'size_int')->textInput() ?>

    <?= $form->field($model, 'map_type')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'seed')->textInput() ?>

    <?= $form->field($model, 'save_version')->textInput() ?>

    <?= $form->field($model, 'is_staging')->checkbox() ?>

    <?= $form->field($model, 'is_custom_map')->checkbox() ?>

    <?= $form->field($model, 'can_download')->checkbox() ?>

    <?= $form->field($model, 'total_monuments')->textInput() ?>

    <?= $form->field($model, 'land_percentage')->textInput() ?>

    <?= $form->field($model, 'islands')->textInput() ?>

    <?= $form->field($model, 'mountains')->textInput() ?>

    <?= $form->field($model, 'ice_lakes')->textInput() ?>

    <?= $form->field($model, 'rivers')->textInput() ?>

    <?= $form->field($model, 'lakes')->textInput() ?>

    <?= $form->field($model, 'canyons')->textInput() ?>

    <?= $form->field($model, 'oases')->textInput() ?>

    <?= $form->field($model, 'buildable_rocks')->textInput() ?>

    <?= $form->field($model, 'raw_image_url')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'image_url')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'image_icon_url')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'thumbnail_url')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'image')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'image_preview')->textInput(['maxlength' => true]) ?>

    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

