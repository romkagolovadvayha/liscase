<?php
/**
 * @var \common\models\template\Template $template
 */
use yii\widgets\ActiveForm;
use yii\helpers\Html;

$this->title = 'Create New Template';
?>

<h1><?= Html::encode($this->title) ?></h1>

<?php $form = ActiveForm::begin(); ?>

<?= $form->field($template, 'name')->textInput() ?>

<div class="form-group">
    <?= Html::submitButton('Create', ['class' => 'btn btn-success']) ?>
    <?= Html::a('Back', ['template/index'], ['class' => 'btn btn-default']) ?>
</div>

<?php ActiveForm::end(); ?>
