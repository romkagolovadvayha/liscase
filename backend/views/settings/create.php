<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'Добавить настройку';
?>

<div class="settings-create">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'name')->textInput() ?>
    <?= $form->field($model, 'category')->textInput() ?>
    <?= $form->field($model, 'type')->dropDownList([
                                                       'text' => 'Текстовое поле',
                                                       'longtext' => 'Много текста',
                                                       'color' => 'Выбор цвета',
                                                       'image' => 'Изображение',
                                                       'video' => 'Видео',
                                                       'number' => 'Числовое поле',
                                                       'checkbox' => 'Чекбокс',
                                                   ]) ?>
    <?= $form->field($model, 'value')->textInput() ?>
    <?= $form->field($model, 'code')->textInput() ?>
    <?= $form->field($model, 'is_translate')->checkbox() ?>

    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>