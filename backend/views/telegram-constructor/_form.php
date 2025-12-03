<?php
use backend\models\TelegramConstructor;
use common\models\user\User;
use common\models\vk\VkUser;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var TelegramConstructor $model */

// Получаем количество получателей для каждой комбинации платформы и аудитории
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
        
        // Показываем индикатор загрузки
        $("#audience-count-info").html("<div class=\'ds-alert ds-alert--info mb-3\'><i class=\'bi bi-hourglass-split\'></i> <strong>Подсчет получателей...</strong></div>").show();
        
        // Делаем AJAX запрос для получения реального количества получателей
        $.ajax({
            url: "/telegram-constructor/get-audience-count",
            method: "GET",
            data: {
                bot_id: botId,
                audience_id: audienceId,
                only_with_user: onlyWithUser ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    var count = response.count;
                    var formatted = response.formatted || count.toLocaleString("ru-RU");
                    
                    if (count > 0) {
                        $("#audience-count-info").html("<div class=\'ds-alert ds-alert--info mb-3\'><i class=\'bi bi-people\'></i> <strong>Получателей:</strong> " + formatted + "</div>").show();
                        
                        // Добавляем ссылку на просмотр аудитории
                        var previewUrl = "/telegram-constructor/preview-audience?bot_id=" + botId + "&audience_id=" + audienceId;
                        if (onlyWithUser && botId == "<?= TelegramConstructor::VK_GROUP ?>") {
                            previewUrl += "&only_with_user=1";
                        }
                        $("#audience-link-info").html("<div class=\'text-center\'><a href=\'" + previewUrl + "\' target=\'_blank\' class=\'ds-btn ds-btn--info ds-btn--sm\'><i class=\'bi bi-list-ul\'></i> Просмотреть список получателей</a></div>").show();
                    } else {
                        $("#audience-count-info").html("<div class=\'ds-alert ds-alert--warning mb-3\'><i class=\'bi bi-exclamation-triangle\'></i> <strong>Внимание:</strong> Нет получателей для выбранной комбинации</div>").show();
                        $("#audience-link-info").html("").hide();
                    }
                } else {
                    $("#audience-count-info").html("<div class=\'ds-alert ds-alert--danger mb-3\'><i class=\'bi bi-exclamation-triangle\'></i> <strong>Ошибка:</strong> " + (response.message || "Не удалось получить количество получателей") + "</div>").show();
                    $("#audience-link-info").html("").hide();
                }
            },
            error: function() {
                $("#audience-count-info").html("<div class=\'ds-alert ds-alert--danger mb-3\'><i class=\'bi bi-exclamation-triangle\'></i> <strong>Ошибка:</strong> Не удалось получить количество получателей</div>").show();
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
                data: {
                    id: message_id,
                },
                success: function(message_preview) {
                    $(".constructor_message_preview").html(message_preview);
                }
            });
        } else {
            $(".constructor_message_preview").html("<div class=\'text-muted text-center p-4\'>Выберите сообщение для предпросмотра</div>");
        }
    }
    
    $(document).on("change", "#telegramconstructor-bot_id, #telegramconstructor-audience_id, #telegramconstructor-only_with_user", function () {
        updateAudienceCount();
    });
    
    $(document).on("change", "#telegramconstructor-telegram_constructor_message_id", function () {
        updateMessagePreview();
    });
    
    // Показываем/скрываем поле only_with_user в зависимости от выбранной платформы
    function toggleOnlyWithUserField() {
        var botId = $("#telegramconstructor-bot_id").val();
        if (botId == "<?= TelegramConstructor::VK_GROUP ?>") {
            $("#telegramconstructor-only_with_user").closest(".form-group").show();
        } else {
            $("#telegramconstructor-only_with_user").closest(".form-group").hide();
        }
    }
    
    $(document).on("change", "#telegramconstructor-bot_id", function () {
        toggleOnlyWithUserField();
    });
    
    // Обновляем при загрузке страницы, если уже выбраны значения
    $(document).ready(function() {
        toggleOnlyWithUserField();
        updateAudienceCount();
        updateMessagePreview();
    });
');
?>

<div class="row">
    <div class="col-lg-6 col-md-8 col-sm-12">
        <?php $form = ActiveForm::begin(
            [
                'id' => 'telegram-constructor-form',
                'options' => ['enctype' => 'multipart/form-data']
            ]); ?>

        <div class="ds-card">
            <div class="ds-card__header">
                <h5 class="ds-card__header-title">Основные параметры рассылки</h5>
            </div>
            <div class="ds-card__body">
                <?= \yii\helpers\Html::errorSummary($model, ['class' => 'ds-alert ds-alert--danger', 'encode' => false]) ?>
                
                <?= $form->field($model, 'title')->textInput([
                    'placeholder' => 'Например: Новогодняя рассылка 2025'
                ])->label('Название рассылки <span class="text-danger">*</span>') ?>

                <div class="row">
                    <div class="col-md-6">
                        <?= $form->field($model, 'bot_id')->dropDownList(
                            $botLabels,
                            [
                                'prompt' => 'Выберите платформу...',
                                'class' => 'form-control ds-input',
                            ]
                        )->label('Платформа <span class="text-danger">*</span>'); ?>
                    </div>
                    <div class="col-md-6">
                        <?= $form->field($model, 'audience_id')->dropDownList(
                            $audienceLabels,
                            [
                                'prompt' => 'Выберите аудиторию...',
                                'class' => 'form-control ds-input',
                            ]
                        )->label('Аудитория <span class="text-danger">*</span>'); ?>
                    </div>
                </div>

                <div id="audience-count-info" style="display: none;"></div>

                <div id="audience-link-info" style="display: none;" class="mb-3"></div>

                <?= $form->field($model, 'telegram_constructor_message_id')->dropDownList(
                    \backend\models\TelegramConstructorMessage::getList(),
                    [
                        'prompt' => 'Выберите сообщение...',
                        'class' => 'form-control ds-input',
                    ]
                )->label('Сообщение <span class="text-danger">*</span>'); ?>

                <?php if ($model->bot_id == TelegramConstructor::VK_GROUP || empty($model->bot_id)): ?>
                    <?= $form->field($model, 'only_with_user')->checkbox([
                        'class' => 'form-check-input'
                    ])->hint('Если отмечено, рассылка будет отправлена только тем VK пользователям, у которых есть привязанный аккаунт в системе (поле vk_id в таблице user).') ?>
                <?php endif; ?>

                <div class="form-group mt-4">
                    <div class="ds-flex ds-items-center ds-gap-md">
                        <?= $this->context->getFormButtons(); ?>
                    </div>
                </div>
            </div>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
    <div class="col-lg-6 col-md-4 col-sm-12">
        <div class="ds-card mb-3">
            <div class="ds-card__header">
                <h5 class="ds-card__header-title">Предпросмотр сообщения</h5>
            </div>
            <div class="ds-card__body">
                <div class="constructor_message_preview">
                    <div class="ds-text--muted text-center p-4">Выберите сообщение для предпросмотра</div>
                </div>
            </div>
        </div>
        
        <div class="ds-card">
            <div class="ds-card__header">
                <h5 class="ds-card__header-title">Справка</h5>
            </div>
            <div class="ds-card__body">
                <h6>Платформы:</h6>
                <ul class="list-unstyled mb-3">
                    <li><strong>Telegram: Персональный бот</strong><br>
                        <small class="text-muted">Рассылка через персонального Telegram бота</small>
                    </li>
                    <li class="mt-2"><strong>ВКонтакте: Группа</strong><br>
                        <small class="text-muted">Рассылка в личные сообщения участников группы ВКонтакте</small>
                    </li>
                </ul>

                <h6>Аудитории:</h6>
                <ul class="list-unstyled">
                    <li><strong>Тестовая аудитория</strong><br>
                        <small class="text-muted">Небольшая группа для тестирования рассылки</small>
                    </li>
                    <li class="mt-2"><strong>Все пользователи</strong><br>
                        <small class="text-muted">Все активные пользователи выбранной платформы</small>
                    </li>
                    <li class="mt-2"><strong>Победители</strong><br>
                        <small class="text-muted">Специальная аудитория победителей</small>
                    </li>
                    <li class="mt-2"><strong>Модераторы и админы</strong><br>
                        <small class="text-muted">Пользователи с ролями модератора или администратора</small>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
