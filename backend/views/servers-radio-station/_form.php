<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use common\models\servers\ServersRadioStation;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersRadioStation $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="servers-radio-station-form">

    <?php $form = ActiveForm::begin([
        'id' => 'servers-radio-station-form',
        'method' => 'post',
        'options' => ['enctype' => 'multipart/form-data'],
        'enableClientValidation' => true,
        'enableAjaxValidation' => false,
    ]); ?>
    
    <?php if ($model->hasErrors()): ?>
        <div class="alert alert-danger">
            <?= Html::errorSummary($model) ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($model, 'sort')->textInput(['type' => 'number']) ?>
        </div>
    </div>

    <?= $form->field($model, 'url')->textInput(['maxlength' => true, 'type' => 'url'])->hint('URL потока радиостанции (например: https://rusradio.hostingradio.ru/rusradio128.mp3)') ?>

    <?php if (!$model->isNewRecord && $model->logo): ?>
        <div class="form-group">
            <label>Текущий логотип:</label><br>
            <?= Html::img($model->getLogoUrl(), ['style' => 'max-width: 200px; max-height: 200px;']) ?>
        </div>
    <?php endif; ?>

    <?= $form->field($model, 'logoFile')->fileInput(['accept' => 'image/*'])->hint($model->isNewRecord ? 'Загрузите логотип радиостанции' : 'Оставьте пустым, чтобы не изменять логотип') ?>

    <?= $form->field($model, 'status')->dropDownList(ServersRadioStation::getStatusList()) ?>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

