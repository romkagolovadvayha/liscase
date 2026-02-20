<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var \common\models\user\User $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="servers-form">

    <?php $form = ActiveForm::begin(); ?>
    <div class="ds-select-wrapper">
        <?= $form->field($model, 'store', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList([
            0 => Yii::t('common', 'Нет'),
            1 => Yii::t('common', 'Да'),
        ], ['class' => 'ds-select form-control']) ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>
    <div class="ds-select-wrapper">
        <?= $form->field($model, 'is_stats', ['options' => ['class' => 'mb-0'], 'template' => '{label}{input}{error}'])->dropDownList([
            0 => Yii::t('common', 'Нет'),
            1 => Yii::t('common', 'Да'),
        ], ['class' => 'ds-select form-control']) ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>
    <div class="form-group">
        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
