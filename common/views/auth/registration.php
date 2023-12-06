<?php

use yii\helpers\Html;
use kartik\form\ActiveForm;
use common\forms\user\RegistrationForm;

/* @var $model RegistrationForm */
$this->title = Yii::t('common', 'Регистрация');
$headerText = Yii::t('common', 'Подтвердите что вы не робот');
$buttonToggle = '<a href="#" class="toggle" title="' . Yii::t('common', 'Показать/Скрыть') . '">' . Yii::t('common', 'Показать/Скрыть') . '</a>';
$buttonSendCode = Html::button(Yii::t('common', 'Отправить код'), ['class'   => 'btn btn-lg btn-info confirm-phone-btn send-code-btn', 'disabled' => true]);
$buttonConfirmCode = Html::button(Yii::t('common', 'Подтвердить'), ['class'   => 'btn btn-lg btn-info confirm-phone-code-btn', 'disabled' => true]);
if ($model->phoneConfirm) {
    $buttonSendCode = "";
}
?>

<main id="main" role="main">
    <div class="content_wrapper container">
        <div class="content">
            <section class="block">
                <header class="block_header">
                    <div class="block_header_container">
                        <h2 class="block_header_container_title"><?= Yii::t('common', 'Регистрация'); ?></h2>
                    </div>
                </header>
                <div class="block_body">
                    <div class="auth_form_registration">
                        <?php $form = ActiveForm::begin([
                                                            'id'          => 'register-form',
                                                            'fieldConfig' => [
                                                                'addClass'        => 'form-control',
                                                                'autoPlaceholder' => true,
                                                                'validateOnType' => true
                                                            ],
                                                        ]); ?>

                        <?= $form->field($model, 'refCode')->textInput() ?>

                        <?= Html::submitButton(Yii::t('common', 'Продолжить регистрацию'), [
                            'id'   => 'registration_button',
                            'class'   => 'btn btn-block btn-info submit_btn',
                            'onclick' => "$('#checkForm').val('no_spam');",
                            'disabled' => true,
                        ]) ?>

                        <?php ActiveForm::end(); ?>

                        <div class="auth_form_other mt-2">
                            <?= Yii::t('common', 'Уже есть аккаунт?'); ?>
                            <a href="/auth/login"><b><?= Yii::t('common', 'Войти') ?></b></a>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>