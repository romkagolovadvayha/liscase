<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $form yii\widgets\ActiveForm */
/* @var $model */
?>

<?php $form = ActiveForm::begin([
                                    'method' => 'GET',
                                    'id' => 'skin_filter',
                                    'options'                => [
                                        'data-pjax' => 1,
                                    ],
                                ]); ?>

<?=$form->field($model, 'name', [
    'inputOptions' => [
        'class' => 'search search_pay'
    ],
    'template' => "{input}{error}"
])
        ->label(false)
        ->textInput(['placeholder' => Yii::t('common', 'Поиск по названию...')]); ?>

<?php ActiveForm::end(); ?>