<?php

use yii\base\BaseObject;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var \frontend\forms\blog\BlogImageForm $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="blog-image-form">
    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>
    <?=$form->field($model, 'stat_key')->textInput()?>
    <?=$form->field($model, 'value')->textInput()?>
    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'ds-btn ds-btn--primary']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
