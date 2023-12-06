<?php

use yii\helpers\Html;
use kartik\form\ActiveForm;

/* @var $model \common\forms\user\LoginForm */

$this->title = Yii::t('common', 'Авторизация');
$buttonToggle = '<a href="#" class="toggle" title="' . Yii::t('common', 'Показать/Скрыть') . '">' . Yii::t('common', 'Показать/Скрыть') . '</a>';
?>

<main id="main" role="main">
    <div class="content_wrapper container">
        <div class="content">
            <section class="block">
                <header class="block_header">
                    <div class="block_header_container">
                        <h2 class="block_header_container_title"><?= Yii::t('common', 'Авторизация'); ?></h2>
                    </div>
                </header>
                <div class="block_body">
                    <div class="auth_form_login">
                        <?php $form = ActiveForm::begin([
                                                            'id'          => 'login-form',
                                                            'fieldConfig' => [
                                                                'autoPlaceholder' => true,
                                                            ],
                                                        ]); ?>
                        <?= $form->field($model, 'email')->label(false)->textInput() ?>
                        <?= $form->field($model, 'password')->passwordInput() ?>
                        <div class="auth_form_login_remember">
                            <?= $form->field($model, 'rememberMe', [
                                'autoPlaceholder'     => false,
                                'template'            => '{input}{label}{hint}',
                                'checkWrapperOptions' => ['class' => 'checkbox checkbox-info mt-0'],
                            ])->label(Yii::t('common', 'Запомнить меня'))->checkbox([], false) ?>
                        </div>
                        <?= Html::submitButton(Yii::t('common', 'Войти'), ['class' => 'btn btn-block btn-info submit_btn', 'name' => 'login-button']) ?>

                        <div class="auth_form_other mt-2">
                            <?= Yii::t('common', 'Ещё нет аккаунта?') ?>
                            <a href="/auth/registration"><?= Yii::t('common', 'Зарегистрироваться') ?></a>
                        </div>

                        <?php ActiveForm::end(); ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>

