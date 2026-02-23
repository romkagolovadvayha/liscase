<?php
use backend\models\TelegramConstructor;
use common\models\user\User;
use common\models\vk\VkUser;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var TelegramConstructor $model */

$telegramTestCount = count(TelegramConstructor::getAudience(TelegramConstructor::AUDIENCE_TEST, TelegramConstructor::PERSONAL_BOT));
$telegramAllCount = User::find()->andWhere('telegram_chat_id IS NOT NULL')->andWhere(['is_telegram_blocked' => 0])->count();
$telegramWinnerCount = count(TelegramConstructor::getAudience(TelegramConstructor::AUDIENCE_WINNER, TelegramConstructor::PERSONAL_BOT));
$telegramModeratorsCount = count(TelegramConstructor::getAudience(TelegramConstructor::AUDIENCE_MODERATORS, TelegramConstructor::PERSONAL_BOT));
$vkTestCount = count(TelegramConstructor::getAudience(TelegramConstructor::AUDIENCE_TEST, TelegramConstructor::VK_GROUP));
$vkAllCount = VkUser::find()->where(['can_send_message' => true])->count();
$vkWinnerCount = count(TelegramConstructor::getAudience(TelegramConstructor::AUDIENCE_WINNER, TelegramConstructor::VK_GROUP));
$vkModeratorsCount = count(TelegramConstructor::getAudience(TelegramConstructor::AUDIENCE_MODERATORS, TelegramConstructor::VK_GROUP));
$audienceLabels = TelegramConstructor::getAudienceList();
$botLabels = TelegramConstructor::getBotList();

$labelOptions = ['class' => 'text-xs text-gray-400 mb-1 block'];
$fieldTemplate = '{label}{input}{error}';

$this->registerJs('
    function updateAudienceCount() {
        var botId = $("#telegramconstructor-bot_id").val();
        var audienceId = $("#telegramconstructor-audience_id").val();
        var onlyWithUser = $("#telegramconstructor-only_with_user").is(":checked");
        if (!botId || !audienceId) {
            $("#audience-count-info").html("").hide();
            $("#audience-link-info").html("").hide();
            return;
        }
        $("#audience-count-info").html("<div class=\'text-sm text-gray-400 py-2\'><i class=\'fas fa-spinner fa-spin\'></i> Подсчёт получателей...</div>").show();
        $.ajax({
            url: "/telegram-constructor/get-audience-count",
            method: "GET",
            data: { bot_id: botId, audience_id: audienceId, only_with_user: onlyWithUser ? 1 : 0 },
            success: function(response) {
                if (response.success) {
                    var count = response.count;
                    var formatted = response.formatted || count.toLocaleString("ru-RU");
                    if (count > 0) {
                        $("#audience-count-info").html("<div class=\'text-sm text-white\'><strong>Получателей:</strong> " + formatted + "</div>").show();
                        var previewUrl = "/telegram-constructor/preview-audience?bot_id=" + botId + "&audience_id=" + audienceId;
                        if (onlyWithUser && botId == "<?= TelegramConstructor::VK_GROUP ?>") previewUrl += "&only_with_user=1";
                        $("#audience-link-info").html("<a href=\'" + previewUrl + "\' target=\'_blank\' class=\'ds-btn ds-btn--info ds-btn--sm\'><i class=\'fas fa-list\'></i> Список получателей</a>").show();
                    } else {
                        $("#audience-count-info").html("<div class=\'text-sm text-amber-400\'><strong>Нет получателей</strong> для выбранной комбинации</div>").show();
                        $("#audience-link-info").html("").hide();
                    }
                } else {
                    $("#audience-count-info").html("<div class=\'text-sm text-red-400\'>" + (response.message || "Ошибка получения количества") + "</div>").show();
                    $("#audience-link-info").html("").hide();
                }
            },
            error: function() {
                $("#audience-count-info").html("<div class=\'text-sm text-red-400\'>Ошибка запроса</div>").show();
                $("#audience-link-info").html("").hide();
            }
        });
    }
    function updateMessagePreview() {
        var message_id = $("#telegramconstructor-telegram_constructor_message_id").val();
        if (message_id && message_id > 0) {
            $.ajax({
                url: "/telegram-constructor-message/get-message-preview",
                method: "GET",
                data: { id: message_id },
                success: function(message_preview) {
                    $(".constructor_message_preview").html(message_preview);
                }
            });
        } else {
            $(".constructor_message_preview").html("<div class=\'text-gray-500 text-sm text-center p-4\'>Выберите сообщение для предпросмотра</div>");
        }
    }
    $(document).on("change", "#telegramconstructor-bot_id, #telegramconstructor-audience_id, #telegramconstructor-only_with_user", function () { updateAudienceCount(); });
    $(document).on("change", "#telegramconstructor-telegram_constructor_message_id", function () { updateMessagePreview(); });
    function toggleOnlyWithUserField() {
        var botId = $("#telegramconstructor-bot_id").val();
        if (botId == "<?= TelegramConstructor::VK_GROUP ?>") {
            $("#telegramconstructor-only_with_user").closest(".form-group").show();
        } else {
            $("#telegramconstructor-only_with_user").closest(".form-group").hide();
        }
    }
    $(document).on("change", "#telegramconstructor-bot_id", function () { toggleOnlyWithUserField(); });
    $(document).ready(function() {
        toggleOnlyWithUserField();
        updateAudienceCount();
        updateMessagePreview();
    });
');
?>

<?php $form = ActiveForm::begin([
    'id' => 'telegram-constructor-form',
    'options' => ['enctype' => 'multipart/form-data'],
]); ?>
<div class="telegram-constructor-form-layout">
<div class="telegram-constructor-form-main">
    <?= Html::errorSummary($model, ['class' => 'ds-alert ds-alert--danger mb-4', 'encode' => false]) ?>

    <?= $form->field($model, 'title', [
        'template' => $fieldTemplate,
        'labelOptions' => $labelOptions,
        'options' => ['class' => 'mb-4'],
    ])->textInput([
        'placeholder' => 'Например: Новогодняя рассылка 2025',
        'class' => 'ds-input form-control w-full',
    ])->label('Название рассылки <span class="text-red-400">*</span>') ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div class="ds-select-wrapper">
            <?= $form->field($model, 'bot_id', [
                'template' => $fieldTemplate,
                'labelOptions' => $labelOptions,
                'options' => ['class' => 'mb-0'],
            ])->dropDownList($botLabels, [
                'prompt' => 'Выберите платформу...',
                'class' => 'ds-select form-control',
            ])->label('Платформа <span class="text-red-400">*</span>') ?>
            <i class="fas fa-chevron-down ds-select-arrow"></i>
        </div>
        <div class="ds-select-wrapper">
            <?= $form->field($model, 'audience_id', [
                'template' => $fieldTemplate,
                'labelOptions' => $labelOptions,
                'options' => ['class' => 'mb-0'],
            ])->dropDownList($audienceLabels, [
                'prompt' => 'Выберите аудиторию...',
                'class' => 'ds-select form-control',
            ])->label('Аудитория <span class="text-red-400">*</span>') ?>
            <i class="fas fa-chevron-down ds-select-arrow"></i>
        </div>
    </div>

    <div id="audience-count-info" class="mb-2" style="display: none;"></div>
    <div id="audience-link-info" class="mb-4" style="display: none;"></div>

    <div class="ds-select-wrapper mb-4">
        <?= $form->field($model, 'telegram_constructor_message_id', [
            'template' => $fieldTemplate,
            'labelOptions' => $labelOptions,
            'options' => ['class' => 'mb-0'],
        ])->dropDownList(\backend\models\TelegramConstructorMessage::getList(), [
            'prompt' => 'Выберите сообщение...',
            'class' => 'ds-select form-control',
        ])->label('Сообщение <span class="text-red-400">*</span>') ?>
        <i class="fas fa-chevron-down ds-select-arrow"></i>
    </div>

    <?php if ($model->bot_id == TelegramConstructor::VK_GROUP || empty($model->bot_id)): ?>
    <div class="form-group mb-4">
        <?= $form->field($model, 'only_with_user', [
            'labelOptions' => ['class' => 'text-sm text-gray-300'],
        ])->checkbox([
            'class' => 'form-check-input',
        ])->hint('Только VK-пользователям с привязанным аккаунтом в системе.') ?>
    </div>
    <?php endif; ?>

    <div class="flex flex-wrap gap-2 items-center mt-4 telegram-constructor-form-actions">
        <?= Html::submitButton('<i class="fas fa-save"></i> ' . Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::a('<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'Отмена'), $this->context->getIndexUrl(), ['class' => 'ds-btn ds-btn--secondary']) ?>
    </div>
</div>

<aside class="telegram-constructor-form-sidebar">
    <div class="telegram-constructor-sidebar-inner">
        <div class="telegram-constructor-sidebar-block telegram-constructor-preview-block">
            <h3 class="telegram-constructor-sidebar-title">Предпросмотр сообщения</h3>
            <div class="constructor_message_preview telegram-constructor-preview-box">
                <div class="telegram-constructor-preview-placeholder">Выберите сообщение для предпросмотра</div>
            </div>
        </div>
        <div class="telegram-constructor-sidebar-block telegram-constructor-help-block">
            <h3 class="telegram-constructor-sidebar-title">Справка</h3>
            <div class="telegram-constructor-help-text">
                <span class="telegram-constructor-help-label">Платформы:</span>
                <ul>
                    <li>Telegram: персональный бот</li>
                    <li>ВКонтакте: группа</li>
                </ul>
                <span class="telegram-constructor-help-label">Аудитории:</span>
                <ul>
                    <li>Тестовая, все пользователи, победители, модераторы</li>
                </ul>
            </div>
        </div>
    </div>
</aside>
</div><!-- .telegram-constructor-form-layout -->

<?php ActiveForm::end(); ?>

<style>
.telegram-constructor-form-layout {
    display: flex;
    flex-direction: row;
    width: 100%;
    flex-wrap: nowrap;
    align-items: stretch;
    min-height: calc(100vh - 140px);
}
.telegram-constructor-form-main {
    flex: 1 1 0%;
    min-width: 0;
    padding: 1rem 1.5rem;
}
.telegram-constructor-form-sidebar {
    width: 420px;
    flex-shrink: 0;
    border-left: 1px solid hsl(0 0% 15.3% / 1);
    background: hsl(0 0% 20.4% / 1);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-height: 100%;
}
.telegram-constructor-sidebar-inner {
    padding: 1rem 1.5rem;
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
}
.telegram-constructor-sidebar-block {
    margin-bottom: 1.5rem;
}
.telegram-constructor-sidebar-block:last-child {
    margin-bottom: 0;
}
.telegram-constructor-preview-block {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
}
.telegram-constructor-help-block {
    flex-shrink: 0;
}
.telegram-constructor-sidebar-title {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #fff;
    margin: 0 0 0.75rem 0;
}
.telegram-constructor-preview-box {
    border-radius: 6px;
    border: 1px solid hsl(0 0% 25% / 1);
    padding: 1rem;
    background: hsl(0 0% 15% / 1);
    font-size: 0.875rem;
    color: hsl(0 0% 70% / 1);
    flex: 1;
    min-height: 200px;
    overflow-y: auto;
    overflow-x: hidden;
}
.telegram-constructor-preview-box img {
    max-width: 100%;
    height: auto;
    display: block;
}
.telegram-constructor-preview-placeholder {
    color: hsl(0 0% 50% / 1);
    text-align: center;
    padding: 1rem 0;
}
.telegram-constructor-help-text {
    font-size: 0.75rem;
    color: hsl(0 0% 65% / 1);
}
.telegram-constructor-help-text ul {
    margin: 0.25rem 0 0.75rem 0;
    padding-left: 1.25rem;
}
.telegram-constructor-help-text ul:last-of-type {
    margin-bottom: 0;
}
.telegram-constructor-help-label {
    color: hsl(0 0% 75% / 1);
    font-weight: 500;
}
@media (max-width: 991px) {
    .telegram-constructor-form-layout {
        flex-direction: column;
        flex-wrap: wrap;
        min-height: 0;
    }
    .telegram-constructor-form-sidebar {
        width: 100%;
        min-height: 0;
        border-left: none;
        border-top: 1px solid hsl(0 0% 15.3% / 1);
    }
    .telegram-constructor-preview-box {
        min-height: 280px;
    }
}
</style>
