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
    <?=$form->field($model, 'image[]')->widget(\kartik\file\FileInput::CLASS, [
        'model' => $model,
        'options' => ['multiple' => false, 'accept' => 'image/png, image/gif, image/jpeg'],
        'language' => 'ru',
        'pluginOptions' => [
            'showPreview' => true,
            'showCaption' => false,
            'showRemove' => false,
            'showUpload' => false,
            'browseIcon' => '<i class="fas fa-camera"></i> ',
            'browseLabel' =>  Yii::t('common', 'Выберите фотографию')
        ]
    ]);?>
    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
