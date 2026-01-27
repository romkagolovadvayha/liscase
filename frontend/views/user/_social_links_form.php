<?php

use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var \common\models\user\UserProfile $profile */
/** @var bool $hasVip */
/** @var \common\models\box\Drop|null $vipDrop */

?>

<?php $form = ActiveForm::begin([
    'id' => 'social-links-form',
    'method' => 'POST',
    'action' => '/user/social-links',
    'options' => [
        'class' => 'social-links-form',
    ],
    'fieldConfig' => [
        'template' => "{input}\n{error}",
    ],
]); ?>

<div class="grid gap-y-24 px-24 mb-24">
    <div class="mb-12">
        <label class="p1 text-text-teritiary mb-7" for="youtube_link">
            <i class="fab fa-youtube"></i> YouTube
        </label>
        <?= Html::input('url', 'youtube_link', $profile->youtube_link, [
            'id' => 'youtube_link',
            'class' => 'search search_pay w-full',
            'placeholder' => 'https://www.youtube.com/@channel'
        ]) ?>
    </div>

    <div class="mb-12">
        <label class="p1 text-text-teritiary mb-7" for="twitch_link">
            <i class="fab fa-twitch"></i> Twitch
        </label>
        <?= Html::input('url', 'twitch_link', $profile->twitch_link, [
            'id' => 'twitch_link',
            'class' => 'search search_pay w-full',
            'placeholder' => 'https://www.twitch.tv/username'
        ]) ?>
    </div>

    <div class="mb-12">
        <label class="p1 text-text-teritiary mb-7" for="vk_link">
            <i class="fab fa-vk"></i> VK
        </label>
        <?= Html::input('url', 'vk_link', $profile->vk_link, [
            'id' => 'vk_link',
            'class' => 'search search_pay w-full',
            'placeholder' => 'https://vk.com/username'
        ]) ?>
    </div>

    <div class="mb-12">
        <label class="p1 text-text-teritiary mb-7" for="telegram_link">
            <i class="fab fa-telegram"></i> Telegram
        </label>
        <?= Html::input('url', 'telegram_link', $profile->telegram_link, [
            'id' => 'telegram_link',
            'class' => 'search search_pay w-full',
            'placeholder' => 'https://t.me/username'
        ]) ?>
    </div>
    
    <div class="mb-12">
        <label class="page-stats__show-statistics-block<?= !$hasVip ? ' disabled' : '' ?>"<?= !$hasVip ? ' data-vip-required="true"' : '' ?>>
            <p class="p1 text-text-teritiary">
                <?= Yii::t('common', 'Скрывать статус онлайн/оффлайн') ?>
                <?php if (!$hasVip): ?>
                    <span class="text-text-secondary" style="font-size: 12px; display: block; margin-top: 4px;">
                        <?= Yii::t('common', 'Доступно только для VIP') ?>
                    </span>
                <?php endif; ?>
            </p>
            <?= Html::hiddenInput('is_hide_online', 0) ?>
            <?= Html::checkbox('is_hide_online', $profile->is_hide_online ?? false, [
                'class' => 'show-statistics-block__switch none',
                'disabled' => !$hasVip,
            ]) ?>
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
                <?php if (!$hasVip): ?>
                    <span class="text-text-secondary" style="font-size: 12px; display: block; margin-top: 4px;">
                        <?= Yii::t('common', 'Доступно только для VIP') ?>
                    </span>
                <?php endif; ?>
            </p>
            <?= Html::hiddenInput('is_hide_team', 0) ?>
            <?= Html::checkbox('is_hide_team', $profile->is_hide_team ?? false, [
                'class' => 'show-statistics-block__switch none',
                'disabled' => !$hasVip,
            ]) ?>
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

<footer class="px-24 pb-24">
    <div class="flex gap-x-12 justify-end">
        <button type="button" class="button button-secondary" data-bs-dismiss="modal">
            <?= Yii::t('common', 'Отмена') ?>
        </button>
        <button type="submit" class="button button-primary">
            <?= Yii::t('common', 'Сохранить') ?>
        </button>
    </div>
</footer>

<?php ActiveForm::end(); ?>

<script>
$(document).ready(function() {
    var formSubmitted = false;
    
    // Обработка клика на disabled переключатель
    $('label[data-vip-required="true"]').on('click', function(e) {
        var checkbox = $(this).find('input[type="checkbox"]');
        if (checkbox.prop('disabled')) {
            e.preventDefault();
            e.stopPropagation();
            var message = "<?= Yii::t('common', 'Для использования этой функции необходимо приобрести VIP') ?>";
            toastr.info("<i class='fas fa-info-circle'></i><div class='toast-message_text'>" + message + "</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
            return false;
        }
    });
    
    // Убираем предыдущие обработчики, если они есть
    $('#social-links-form').off('submit').on('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Защита от повторной отправки
        if (formSubmitted) {
            return false;
        }
        
        var form = $(this);
        var formData = form.serialize();
        formSubmitted = true;
        
        // Блокируем кнопку отправки
        var submitBtn = form.find('button[type="submit"]');
        submitBtn.prop('disabled', true);
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success("<i class='fas fa-check-circle'></i><div class='toast-message_text'>" + response.message + "</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
                    var modalEl = document.getElementById('modal-dialog');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) {
                        modal.hide();
                    }
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    toastr.error("<i class='fas fa-exclamation-circle'></i><div class='toast-message_text'>" + response.message + "</div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
                    formSubmitted = false;
                    submitBtn.prop('disabled', false);
                }
            },
            error: function() {
                toastr.error("<i class='fas fa-exclamation-circle'></i><div class='toast-message_text'><?= Yii::t('common', 'Ошибка при сохранении') ?></div>", '', { progressBar: true, positionClass: 'toast-top-right', escapeHtml: false });
                formSubmitted = false;
                submitBtn.prop('disabled', false);
            }
        });
        
        return false;
    });
});
</script>

