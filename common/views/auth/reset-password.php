<?php

use yii\helpers\Html;
use kartik\form\ActiveForm;

/* @var $model common\forms\user\ResetPasswordForm */

$this->title = Yii::t('common', 'Сброс пароля');

?>

    <div class="row">
        <div class="col-xs-12">
            <h3 class="mt-5 text-center"><?= $this->title; ?></h3>
        </div>
    </div>

<?php $form = ActiveForm::begin([
    'id'          => 'reset-password-form',
    'fieldConfig' => [
        'addClass'        => 'form-control form-control-lg',
        'autoPlaceholder' => true,
    ],
]); ?>

<?= $form->field($model, 'password', [
    'addon' => [
        'prepend' => [
            'content' => '<i class="ti-lock"></i>',
        ],
    ],
])->passwordInput() ?>

<?= $form->field($model, 'passwordRepeat', [
    'addon' => [
        'prepend' => [
            'content' => '<i class="ti-lock"></i>',
        ],
    ],
])->passwordInput() ?>

    <div class="form-group">
        <?= Html::submitButton(Yii::t('common', 'Сохранить'), ['class' => 'btn btn-block btn-lg btn-info']) ?>
    </div>

<?php ActiveForm::end(); ?>