<?php

use yii\base\BaseObject;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\web\JsExpression;

/** @var yii\web\View $this */
/** @var \common\models\box\DropDrop $model */
/** @var yii\widgets\ActiveForm $form */

$searchJS = \common\models\box\Drop::searchJS();
?>

<div class="blog-image-form">
    <?php $form = ActiveForm::begin([
        'options' => [
            'id' => 'ajaxCrudModal', 
            'enctype' => 'multipart/form-data',
            'data-pjax' => true
        ]
    ]); ?>
    <?php \yii\widgets\Pjax::begin([
        'id' => 'drop-drop-form-pjax',
        'enablePushState' => false,
        'timeout' => 5000
    ]); ?>
    <?= $form->field($model, 'drop_id')->widget(\kartik\select2\Select2::class, [
        'data'    => \common\models\box\Drop::getDropList(),
        'options' => [
            'placeholder' => 'Выберите предмет...',
            'multiple' => false,
            'debug' => true,
        ],
        'showToggleAll' => true,
        'pluginOptions' => [
            'templateResult'       => $searchJS['templateResult'],
            'templateSelection' => $searchJS['templateSelection'],
            'escapeMarkup' => $searchJS['escapeMarkup'],
            'allowClear' => true,
            'ajax' => [
                'url' => '/drop/search-drop',
                'dataType' => 'json',
                'delay' => 250,
                'data' => $searchJS['ajaxData'],
                'processResults' => $searchJS['processResults'],
                'cache' => true
            ],
            'debug' => true,
            'dropdownParent' => new yii\web\JsExpression('$("#ajaxCrudModal")'),
        ],
    ]); ?>
    <?=$form->field($model, 'count')->textInput()?>
    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'ds-btn ds-btn--primary']) ?>
    </div>
    <?php \yii\widgets\Pjax::end(); ?>
    <?php ActiveForm::end(); ?>
</div>
