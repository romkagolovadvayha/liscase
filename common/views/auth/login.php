<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

/* @var $model \common\forms\user\LoginForm */

$this->title = Yii::t('common', 'Авторизация');
?>
<style>
    .auth_form_login {
        max-width: 100%;
        width: 600px;
        margin: 0 auto;
        margin-top: 30px;
    }
</style>
<div class="auth_form_login">
    <?php $form = ActiveForm::begin([
        'id'          => 'login-form',
    ]); ?>
    <?= $form->field($model, 'email')->textInput() ?>
    <div class="toggle_wrapper">
        <?= $form->field($model, 'password')->passwordInput() ?>
    </div>
    <div class="auth_form_login_remember">
        <?= $form->field($model, 'rememberMe', [
            'template'            => '{input}{label}{hint}',
        ])->label(Yii::t('common', 'Запомнить меня'))->checkbox([], false) ?>
    </div>
    <?= Html::submitButton(Yii::t('common', 'Войти'), ['class' => 'btn btn-block btn-lg btn-info submit_btn', 'name' => 'login-button']) ?>
</div>

<?php ActiveForm::end(); ?>

