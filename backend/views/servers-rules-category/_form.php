<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use common\models\servers\ServersRulesCategory;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersRulesCategory $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="servers-rules-category-form">

    <?php $form = ActiveForm::begin([
        'id' => 'servers-rules-category-form',
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
        <div class="col-md-6">
            <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($model, 'icon')->textInput([
                'maxlength' => true,
                'placeholder' => 'Например: shield, user-shield, terminal'
            ])->hint('Название иконки из компонента Icon (например: shield, user-shield, terminal)') ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($model, 'sort')->textInput(['type' => 'number']) ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <?= $form->field($model, 'no_numbering')->checkbox()->hint('Если включено, правила в этой категории не будут иметь нумерацию (например, для категории "Команды на сервере")') ?>
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

