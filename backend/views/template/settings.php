<?php
/**
 * @var \common\models\template\Template $template
 */
use yii\widgets\ActiveForm;
use yii\helpers\Html;

$this->title = 'Edit Template: ' . Html::encode($template->name);
?>

<h1><?= Html::encode($this->title) ?></h1>

<?php $form = ActiveForm::begin(); ?>

<?= $form->field($template, 'name')->textInput() ?>

<div class="form-group">
    <?= Html::submitButton('Save', ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Back', ['template/index', 'templateId' => (int)$template->id], ['class' => 'btn btn-default']) ?>
</div>

<?php ActiveForm::end(); ?>
