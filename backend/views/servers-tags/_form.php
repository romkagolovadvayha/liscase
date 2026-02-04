<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use common\models\servers\ServersTags;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersTags $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="servers-tags-form">

    <?php $form = ActiveForm::begin([
        'id' => 'servers-tags-form',
        'method' => 'post',
        'enableClientValidation' => true,
        'enableAjaxValidation' => false,
    ]); ?>
    
    <?php if ($model->hasErrors()): ?>
        <div class="alert alert-danger">
            <?= Html::errorSummary($model) ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
        </div>
        <div class="col-md-4">
            <?= $form->field($model, 'title')->textInput(['maxlength' => true]) ?>
        </div>
        <div class="col-md-4">
            <?= $form->field($model, 'link_name')->textInput(['maxlength' => true]) ?>
        </div>
    </div>

    <?= $form->field($model, 'short_description')->textInput(['maxlength' => true]) ?>


    <?= $form->field($model, 'description')->widget(\dosamigos\tinymce\TinyMce::class, [
        'options' => ['rows' => 20],
        'language' => 'ru',
        'clientOptions' => [
            'plugins' => [
                'advlist','autolink','lists','link','media',
                'table','codesample','code','emoticons','paste','autoresize','quickbars'
            ],
            'toolbar' => 'undo redo | styles | bold italic underline | ' .
                'alignleft aligncenter alignright alignjustify | ' .
                'bullist numlist outdent indent | table | link image media | ' .
                'codesample code emoticons',
            'menubar' => 'file edit view insert format tools table',
            'statusbar' => true,
            'resize' => true,
            'default_link_target' => '_blank',
            'link_context_toolbar' => true,
            'convert_urls' => false,
        ],
    ]); ?>

    <div class="row">
        <div class="col-md-4">
            <?= $form->field($model, 'color')->textInput([
                'maxlength' => true,
                'type' => 'text',
                'value' => $model->isNewRecord ? '#3498db' : $model->color
            ]) ?>
        </div>
        <div class="col-md-4">
            <?= $form->field($model, 'icon')->textInput([
                'maxlength' => true,
                'placeholder' => 'Например: star, heart, shield'
            ])->hint('Название иконки из компонента Icon (например: star, heart, shield)') ?>
        </div>
        <div class="col-md-4">
            <?= $form->field($model, 'sort')->textInput(['type' => 'number']) ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <?= $form->field($model, 'status')->dropDownList(ServersTags::getStatusList()) ?>
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>
    
    <?php if (YII_DEBUG): ?>
        <div class="alert alert-info" style="margin-top: 20px;">
            <strong>Отладочная информация:</strong><br>
            CSRF Param: <?= Yii::$app->request->csrfParam ?><br>
            CSRF Token: <?= substr(Yii::$app->request->csrfToken, 0, 20) ?>...<br>
            POST данные: <?= $model->isNewRecord ? 'Новая запись' : 'Редактирование #' . $model->id ?>
        </div>
    <?php endif; ?>

</div>

