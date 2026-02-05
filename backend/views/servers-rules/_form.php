<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use common\models\servers\ServersRules;
use common\models\servers\ServersRulesCategory;
use common\models\servers\Servers;
use yii\helpers\ArrayHelper;

/** @var yii\web\View $this */
/** @var common\models\servers\ServersRules $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="servers-rules-form">

    <?php $form = ActiveForm::begin([
        'id' => 'servers-rules-form',
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
            <?= $form->field($model, 'category_id')->dropDownList(
                ArrayHelper::map(ServersRulesCategory::find()->orderBy(['sort' => SORT_ASC, 'name' => SORT_ASC])->all(), 'id', 'name'),
                ['prompt' => 'Выберите категорию']
            ) ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <?php
            // Получаем список серверов
            $serversList = ArrayHelper::map(Servers::find()->orderBy(['sort' => SORT_ASC, 'name' => SORT_ASC])->all(), 'id', 'name');
            ?>
            <?= $form->field($model, 'serverIds')->checkboxList(
                $serversList,
                [
                    'item' => function($index, $label, $name, $checked, $value) {
                        return '<div class="checkbox" style="margin-bottom: 8px;">' .
                            Html::checkbox($name, $checked, ['value' => $value, 'id' => 'server-' . $value]) .
                            ' ' .
                            Html::label($label, 'server-' . $value, ['style' => 'font-weight: normal; margin-left: 5px;']) .
                            '</div>';
                    }
                ]
            )->hint('Выберите серверы для этого правила. Если ничего не выбрано, правило будет общим для всех серверов. Можно выбрать несколько серверов.') ?>
        </div>
    </div>

    <?= $form->field($model, 'title')->textInput(['maxlength' => true])->hint('Опционально. Название правила, если нужно') ?>

    <?= $form->field($model, 'content')->widget(\dosamigos\tinymce\TinyMce::class, [
        'options' => ['rows' => 10],
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
    ])->hint('Содержание правила в формате HTML') ?>

    <div class="row">
        <div class="col-md-6">
            <?= $form->field($model, 'punishment')->textInput(['maxlength' => true])->hint('Например: [ban], [mute], [ban 14d], [предупреждение]') ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($model, 'sort')->textInput(['type' => 'number']) ?>
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

