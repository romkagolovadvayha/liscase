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
            <div class="mb-12">
                <label class="page-stats__show-statistics-block">
                    <p class="p1 text-text-teritiary"><?=Yii::t('common', "Оповещать о банах игроков, на которых вы отправили жалобу")?></p>
                    <?=Html::hiddenInput('ProfileForm[ban_notify]', 0)?>
                    <?=Html::checkbox('ProfileForm[ban_notify]', $model->ban_notify, ['class' => 'show-statistics-block__switch none'])?>
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
            
            <!-- Поля для социальных сетей -->
            <div class="mt-24">
                <label class="p1 text-text-teritiary mb-7" for="profileform-youtube_link">
                    <?=Yii::t('common', "Ссылка на YouTube канал")?>
                </label>
                <div style="max-width: 700px; margin-bottom: 16px;">
                    <?=$form->field($model, 'youtube_link', [
                        'inputOptions' => [
                            'class' => 'search search_pay'
                        ],
                        'template' => "{input}{error}"
                    ])->label(false)->textInput(['placeholder' => 'https://youtube.com/@yourchannel']); ?>
                </div>
                
                <label class="p1 text-text-teritiary mb-7" for="profileform-tiktok_link">
                    <?=Yii::t('common', "Ссылка на TikTok")?>
                </label>
                <div style="max-width: 700px; margin-bottom: 16px;">
                    <?=$form->field($model, 'tiktok_link', [
                        'inputOptions' => [
                            'class' => 'search search_pay'
                        ],
                        'template' => "{input}{error}"
                    ])->label(false)->textInput(['placeholder' => 'https://tiktok.com/@username']); ?>
                </div>
                
                <label class="p1 text-text-teritiary mb-7" for="profileform-twitch_link">
                    <?=Yii::t('common', "Ссылка на Twitch канал")?>
                </label>
                <div style="max-width: 700px; margin-bottom: 16px;">
                    <?=$form->field($model, 'twitch_link', [
                        'inputOptions' => [
                            'class' => 'search search_pay'
                        ],
                        'template' => "{input}{error}"
                    ])->label(false)->textInput(['placeholder' => 'https://twitch.tv/username']); ?>
                </div>
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
        <div>
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
        
        <div class="mb-12">
            <div class="flex gap-x-10 align-items-center flex-wrap">
                <p class="p1 text-text-teritiary"><?=Yii::t('common', "Discord")?></p>
                <?php if (!empty($model->user->discord_id)): ?>
                    <button type="submit" name="ProfileForm[discord_disabled]" value="1" class="button-secondary button-size__s h-36" style="padding-top: 6px; padding-bottom: 6px"><span class="button__text"><?=Yii::t('common', 'Отвязать аккаунт')?></span></button>
                <?php else: ?>
                    <a href="<?=\yii\helpers\Url::to(['/auth/discord'])?>" class="button button-secondary button-size__s h-36" style="padding-top: 6px; padding-bottom: 6px"><span class="button__text"><?=Yii::t('common', 'Привязать аккаунт')?></span></a>
                <?php endif; ?>
            </div>
            </div>
            
            <div class="mb-24" style="border-top: 1px solid var(--border-color-default); padding-top: 24px; margin-top: 24px;">
            <p class="p1 text-text-secondary mb-16" style="font-size: 13px;">
                <?=Yii::t('common', "Укажите ссылки на ваши профили в социальных сетях. Они будут отображаться на вашей странице статистики, чтобы другие игроки могли найти вас.")?>
            </p>
            
            <div class="mb-12">
                <label class="p1 text-text-teritiary mb-7" for="profileform-youtube_link">
                    <i class="fab fa-youtube"></i> <?=Yii::t('common', "YouTube канал")?>
                </label>
                <div style="max-width: 700px;">
                    <?=$form->field($model, 'youtube_link', [
                        'inputOptions' => [
                            'class' => 'search search_pay'
                        ],
                        'template' => "{input}{error}"
                    ])->label(false)->textInput(['placeholder' => 'https://www.youtube.com/@channel']); ?>
                </div>
            </div>
            
            <div class="mb-12">
                <label class="p1 text-text-teritiary mb-7" for="profileform-twitch_link">
                    <i class="fab fa-twitch"></i> <?=Yii::t('common', "Twitch канал")?>
                </label>
                <div style="max-width: 700px;">
                    <?=$form->field($model, 'twitch_link', [
                        'inputOptions' => [
                            'class' => 'search search_pay'
                        ],
                        'template' => "{input}{error}"
                    ])->label(false)->textInput(['placeholder' => 'https://www.twitch.tv/username']); ?>
                </div>
            </div>
            
            <div class="mb-12">
                <label class="p1 text-text-teritiary mb-7" for="profileform-vk_link">
                    <i class="fab fa-vk"></i> <?=Yii::t('common', "VK профиль")?>
                </label>
                <div style="max-width: 700px;">
                    <?=$form->field($model, 'vk_link', [
                        'inputOptions' => [
                            'class' => 'search search_pay'
                        ],
                        'template' => "{input}{error}"
                    ])->label(false)->textInput(['placeholder' => 'https://vk.com/username']); ?>
                </div>
            </div>
            
            <div class="mb-12">
                <label class="p1 text-text-teritiary mb-7" for="profileform-telegram_link">
                    <i class="fab fa-telegram"></i> <?=Yii::t('common', "Telegram канал или группа")?>
                </label>
                <div style="max-width: 700px;">
                    <?=$form->field($model, 'telegram_link', [
                        'inputOptions' => [
                            'class' => 'search search_pay'
                        ],
                        'template' => "{input}{error}"
                    ])->label(false)->textInput(['placeholder' => 'https://t.me/username']); ?>
                </div>
            </div>
            </div>
            
            <?php 
            $hasVip = $model->user->hasVip();
            $vipDrop = \common\models\box\Drop::find()
                ->where(['drop_type' => \common\models\box\Drop::TYPE_VIP])
                ->andWhere(['market_status' => \common\models\box\Drop::MARKET_STATUS_ACTIVE])
                ->andWhere(['status' => \common\models\box\Drop::STATUS_ACTIVE])
                ->orderBy(['sort' => SORT_ASC])
                ->one();
            ?>
            
            <div class="mb-24" style="border-top: 1px solid var(--border-color-default); padding-top: 24px; margin-top: 24px;">
            <h5 class="p1 text-text-main mb-16" style="font-weight: 600;">
                <?=Yii::t('common', "Настройки приватности")?>
            </h5>
            <p class="p1 text-text-secondary mb-16" style="font-size: 13px;">
                <?=Yii::t('common', "Эти настройки позволяют скрыть определенную информацию о вас от других игроков. Доступны только для пользователей с VIP статусом.")?>
            </p>
            
            <div class="mb-12">
                <label class="page-stats__show-statistics-block<?= !$hasVip ? ' disabled' : '' ?>"<?= !$hasVip ? ' data-vip-required="true"' : '' ?>>
                    <p class="p1 text-text-teritiary">
                        <?= Yii::t('common', 'Скрывать статус онлайн/оффлайн') ?>
                        <span class="text-text-secondary" style="font-size: 12px; display: block; margin-top: 4px;">
                            <?= Yii::t('common', 'Если включено, другие игроки не увидят, находитесь ли вы сейчас в игре') ?>
                        </span>
                        <?php if (!$hasVip): ?>
                            <span class="text-text-secondary" style="font-size: 12px; display: block; margin-top: 4px; color: var(--primary-colors-main);">
                                <?= Yii::t('common', 'Доступно только для VIP') ?>
                            </span>
                        <?php endif; ?>
                    </p>
                    <?=Html::hiddenInput('ProfileForm[is_hide_online]', 0)?>
                    <?=Html::checkbox('ProfileForm[is_hide_online]', $model->is_hide_online ?? false, [
                        'class' => 'show-statistics-block__switch none',
                        'disabled' => !$hasVip,
                    ])?>
                    <span>
                        <span class="icons icons_switch icons_switch_on"></span>
                        <span class="icons icons_switch icons_switch_off"></span>
                    </span>
                </label>
                <?php if (!$hasVip && $vipDrop): ?>
                    <div class="mt-8">
                        <a href="/market/form-modal?id=<?= $vipDrop->id ?>" 
                           class="button button-primary show-modal-link" 
                           data-size="modal-sm"
                           data-toggl="modal"
                           data-target="modal-dialog"
                           data-title="<?= Yii::t('database', $vipDrop->name) ?>"
                           style="font-size: 13px; padding: 8px 16px;">
                            <i class="fas fa-crown"></i> <?= Yii::t('common', 'Купить VIP') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mb-12">
                <label class="page-stats__show-statistics-block<?= !$hasVip ? ' disabled' : '' ?>"<?= !$hasVip ? ' data-vip-required="true"' : '' ?>>
                    <p class="p1 text-text-teritiary">
                        <?= Yii::t('common', 'Скрывать список команды') ?>
                        <span class="text-text-secondary" style="font-size: 12px; display: block; margin-top: 4px;">
                            <?= Yii::t('common', 'Если включено, другие игроки не увидят список участников вашей команды') ?>
                        </span>
                        <?php if (!$hasVip): ?>
                            <span class="text-text-secondary" style="font-size: 12px; display: block; margin-top: 4px; color: var(--primary-colors-main);">
                                <?= Yii::t('common', 'Доступно только для VIP') ?>
                            </span>
                        <?php endif; ?>
                    </p>
                    <?=Html::hiddenInput('ProfileForm[is_hide_team]', 0)?>
                    <?=Html::checkbox('ProfileForm[is_hide_team]', $model->is_hide_team ?? false, [
                        'class' => 'show-statistics-block__switch none',
                        'disabled' => !$hasVip,
                    ])?>
                    <span>
                        <span class="icons icons_switch icons_switch_on"></span>
                        <span class="icons icons_switch icons_switch_off"></span>
                    </span>
                </label>
                <?php if (!$hasVip && $vipDrop): ?>
                    <div class="mt-8">
                        <a href="/market/form-modal?id=<?= $vipDrop->id ?>" 
                           class="button button-primary show-modal-link" 
                           data-size="modal-sm"
                           data-toggl="modal"
                           data-target="modal-dialog"
                           data-title="<?= Yii::t('database', $vipDrop->name) ?>"
                           style="font-size: 13px; padding: 8px 16px;">
                            <i class="fas fa-crown"></i> <?= Yii::t('common', 'Купить VIP') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            </div>
            
            <button type="submit" class="button-secondary"><?=Yii::t('common', 'Сохранить')?></button>
            <?php ActiveForm::end(); ?>
            <?php Pjax::end(); ?>
        </div>
    </section>
</section>

<script>
(function() {
    // Ждем загрузки DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        // Обработка клика на disabled переключатель (только для VIP функций)
        var vipLabels = document.querySelectorAll('label[data-vip-required="true"]');
        vipLabels.forEach(function(label) {
            label.addEventListener('click', function(e) {
                var checkbox = this.querySelector('input[type="checkbox"]');
                if (checkbox && checkbox.disabled) {
                    e.preventDefault();
                    e.stopPropagation();
                    var message = "<?= Yii::t('common', 'Для использования этой функции необходимо приобрести VIP') ?>";
                    
                    // Проверяем наличие toastr
                    if (typeof toastr !== 'undefined' && typeof jQuery !== 'undefined') {
                        jQuery(function($) {
                            toastr.info("<i class='fas fa-info-circle'></i><div class='toast-message_text'>" + message + "</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
                        });
                    } else {
                        alert(message);
                    }
                    return false;
                }
            });
        });
    }
})();
</script>