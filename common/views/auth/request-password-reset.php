<?php

use yii\helpers\Html;
use kartik\form\ActiveForm;

/* @var $model \common\forms\user\PasswordResetRequestForm */

$this->title = Yii::t('common', 'Восстановление пароля');

?>

<?php $form = ActiveForm::begin([
    'id'          => 'request-reset-password-form',
    'fieldConfig' => [
        'addClass'        => 'form-control form-control-lg',
        'autoPlaceholder' => true,
    ],
]); ?>

<?= $form->field($model, 'email')->textInput() ?>

<?= Html::submitButton(Yii::t('common', 'Восстановить пароль'), ['class' => 'btn btn-block btn-lg btn-info submit_btn']) ?>

<?php ActiveForm::end(); ?>

<div class="mb-5 mt-4">
    <div class="col-sm-12 text-center">
        <a href="/auth/login" class="text-info ml-2">
            <b><?= Yii::t('common', 'На страницу авторизации') ?></b>
        </a>
    </div>
</div>
