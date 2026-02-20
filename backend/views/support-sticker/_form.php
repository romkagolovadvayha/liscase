<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use common\models\support\SupportSticker;

/** @var yii\web\View $this */
/** @var common\models\support\SupportSticker $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="support-sticker-form">

    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]); ?>

    <?= $form->field($model, 'code')->textInput(['maxlength' => true])->hint('Уникальный код стикера (например: smile, thumbs_up)') ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true]) ?>

    <?php if (!$model->isNewRecord && $model->file): ?>
        <div class="form-group">
            <label>Текущий файл:</label><br>
            <?php if ($model->type === SupportSticker::TYPE_IMAGE): ?>
                <?= Html::img($model->getPublicUrl(), ['style' => 'max-width: 200px; max-height: 200px;']) ?>
            <?php else: ?>
                <?= Html::tag('video', '', [
                    'src' => $model->getPublicUrl(),
                    'style' => 'max-width: 200px; max-height: 200px;',
                    'controls' => true
                ]) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?= $form->field($model, 'file')->fileInput(['accept' => 'image/*,video/*'])->hint($model->isNewRecord ? 'Загрузите файл стикера (изображение или видео)' : 'Оставьте пустым, чтобы не изменять файл') ?>

    <div class="ds-select-wrapper">
        <?= $form->field($model, 'type', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{hint}{error}'])->dropDownList(SupportSticker::getTypeList(), ['class' => 'ds-select form-control', 'prompt' => 'Выберите тип...'])->hint('Тип определяется автоматически при загрузке файла') ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>

    <div class="row">
        <div class="col-md-6">
            <?= $form->field($model, 'width')->textInput(['type' => 'number'])->hint('Ширина в пикселях (опционально)') ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($model, 'height')->textInput(['type' => 'number'])->hint('Высота в пикселях (опционально)') ?>
        </div>
    </div>

    <?= $form->field($model, 'sort')->textInput(['type' => 'number'])->hint('Порядок сортировки (меньше = выше в списке)') ?>

    <div class="ds-select-wrapper">
        <?= $form->field($model, 'status', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList(SupportSticker::getStatusList(), ['class' => 'ds-select form-control']) ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>

    <div class="form-group">
        <?= Html::submitButton(Yii::t('common', 'Сохранить'), ['class' => 'btn btn-success']) ?>
        <?php if (!$model->isNewRecord): ?>
            <?= Html::a(Yii::t('common', 'Отмена'), ['view', 'id' => $model->id], ['class' => 'btn btn-default']) ?>
        <?php else: ?>
            <?= Html::a(Yii::t('common', 'Отмена'), ['index'], ['class' => 'btn btn-default']) ?>
        <?php endif; ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>


































