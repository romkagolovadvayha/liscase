<?php

use frontend\forms\profile\ProfileForm;
use yii\web\View;
use frontend\widgets\Alert;
use yii\bootstrap5\ActiveForm;
use yii\widgets\Pjax;
use yii\helpers\Html;

/** @var View $this */
/** @var ProfileForm $model */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Профиль") . " - {$user->userProfile->name}";
?>
<section class="tasks">
    <h2 class="tasks__title">
        <?=Yii::t('common', 'Профиль')?>
    </h2>
    <section class="page-stats__block-without-hover">
        <h4 class="flex items-center gap-x-12 mb-24">
            <?=Yii::t('common', "Общие настройки")?>
        </h4>
        <div>
            <?php Pjax::begin(['id' => 'profile-form-pjax']); ?>
            <?php $form = ActiveForm::begin([
                                                'method' => 'POST',
                                                'id' => 'profile-form',
                                                'options'                => [
                                                    'data-pjax' => 1,
                                                ],
                                            ]); ?>
            <?= Alert::widget() ?>
            <div class="mb-12">
                <label class="page-stats__show-statistics-block">
                    <p class="p1 text-text-teritiary"><?=Yii::t('common', "Включить оповещения о рейдах")?></p>
                    <?=Html::hiddenInput('ProfileForm[raid_notify]', 0)?>
                    <?=Html::checkbox('ProfileForm[raid_notify]', $model->raid_notify, ['class' => 'show-statistics-block__switch none'])?>
                    <span>
                        <span class="icons icons_switch icons_switch_on"></span>
                        <span class="icons icons_switch icons_switch_off"></span>
                    </span>
                </label>
            </div>
            <label class="p1 text-text-teritiary mb-7" for="profileform-trade_link">
                <?=Yii::t('common', "Ваша")?> <a class="p1" href="https://steamcommunity.com/id/me/tradeoffers/privacy#trade_offer_access_url" target="_blank"><?=Yii::t('common', "трейд ссылка")?></a> <?=Yii::t('common', "на обмен")?>
            </label>
            <div  style="max-width: 700px;">
                <?=$form->field($model, 'trade_link', [
                    'inputOptions' => [
                        'class' => 'search search_pay'
                    ],
                    'template' => "{input}{error}"
                ])->label(false)->textInput(['placeholder' => Yii::t('common', 'https://steamcommunity.com/id/me/tradeoffers/privacy#trade_offer_access_url')]); ?>
            </div>
            <button type="submit" class="button-secondary"><?=Yii::t('common', 'Сохранить')?></button>
            <?php ActiveForm::end(); ?>
            <?php Pjax::end(); ?>
        </div>
    </section>
    <section class="page-stats__block-without-hover mt-40">
        <h4 class="flex items-center gap-x-12 mb-24">
            <?=Yii::t('common', "Социальные сети")?>
        </h4>

        <?php Pjax::begin(['id' => 'social-form-pjax']); ?>
        <?php $form = ActiveForm::begin([
                                            'method' => 'POST',
                                            'id' => 'social-form',
                                            'options'                => [
                                                'data-pjax' => 1,
                                            ],
                                        ]); ?>
        <?= Alert::widget() ?>
        <div class="mb-12">
            <div class="flex gap-x-10 align-items-center flex-wrap">
                <p class="p1 text-text-teritiary"><?=Yii::t('common', "Персональный телеграм бот")?></p>
                <?php if (!empty($model->user->telegram_chat_id)): ?>
                    <button type="submit" name="ProfileForm[telegram_disabled]" value="1" class="button-secondary button-size__s h-36" style="padding-top: 6px; padding-bottom: 6px"><span class="button__text"><?=Yii::t('common', 'Отвязать аккаунт')?></span></button>
                <?php else: ?>
                    <a href="https://t.me/<?=Yii::$app->settings->get('tgbot_login')?>" target="_blank" class="button button-secondary button-size__s h-36" style="padding-top: 6px; padding-bottom: 6px"><span class="button__text"><?=Yii::t('common', 'Привязать аккаунт')?></span></a>
                <?php endif; ?>
            </div>
        </div>
        <?php ActiveForm::end(); ?>
        <?php Pjax::end(); ?>
    </section>
</section>