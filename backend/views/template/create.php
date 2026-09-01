<?php
/**
 * @var \common\models\template\Template $template
 */
use yii\widgets\ActiveForm;
use yii\helpers\Html;

$this->title = 'Создать шаблон';
?>

<h1><?= Html::encode($this->title) ?></h1>

<?php $form = ActiveForm::begin(); ?>

<?= $form->field($template, 'name')->textInput() ?>

<div class="form-group">
    <?= Html::submitButton('Создать', ['class' => 'ds-btn ds-btn--primary']) ?>
    <?= Html::a('Назад', ['template/index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
</div>

<?php ActiveForm::end(); ?>
