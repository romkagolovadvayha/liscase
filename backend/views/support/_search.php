<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var backend\models\support\SupportSearch $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="support-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
        'options' => [
            'data-pjax' => 1
        ],
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'user_id') ?>

    <?= $form->field($model, 'name') ?>

    <?= $form->field($model, 'status') ?>

    <?= $form->field($model, 'created_at') ?>

    <div class="form-group">
        <?= Html::submitButton('Найти', ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::resetButton('Сбросить', ['class' => 'ds-btn ds-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
