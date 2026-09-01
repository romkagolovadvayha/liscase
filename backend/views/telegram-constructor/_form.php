<?php

use backend\models\TelegramConstructor;
use backend\models\TelegramConstructorMessage;
use common\components\helpers\Role;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

/** @var TelegramConstructor $model */

$audienceLabels = TelegramConstructor::getAudienceList();
$botLabels = TelegramConstructor::getBotList();
$messageLabels = TelegramConstructorMessage::getList();
$formTitle = $model->isNewRecord ? 'Новая рассылка' : 'Редактирование черновика';
$countUrl = Url::to(['/telegram-constructor/get-audience-count']);
$audienceUrl = Url::to(['/telegram-constructor/preview-audience']);
$messageUrl = Url::to(['/telegram-constructor-message/get-message-preview']);
?>
<div class="mailing-page mailing-compose-page">
    <?= $this->render('_section_nav') ?>

    <header class="mailing-page-head mailing-page-head--compact">
        <div>
            <span class="mailing-page-head__eyebrow">Рассылка</span>
            <h1><?= Html::encode($formTitle) ?></h1>
            <p>Настройте получателей и сообщение. Отправка начнётся только после проверки на следующем экране.</p>
        </div>
    </header>

    <?php $form = ActiveForm::begin([
        'id' => 'telegram-constructor-form',
        'options' => [
            'class' => 'mailing-compose mailing-compose--simple',
            'data-audience-count-url' => $countUrl,
            'data-audience-preview-url' => $audienceUrl,
            'data-message-preview-url' => $messageUrl,
            'data-vk-bot-id' => TelegramConstructor::VK_GROUP,
        ],
    ]) ?>
        <main class="mailing-compose__main">
            <?= Html::errorSummary($model, [
                'class' => 'ds-alert ds-alert--danger mailing-form-errors',
                'header' => '<strong>Не удалось сохранить черновик</strong><span>Исправьте отмеченные поля.</span>',
                'encode' => false,
            ]) ?>

            <section class="mailing-compose-sheet" aria-labelledby="mailing-settings-title">
                <header class="mailing-compose-sheet__head">
                    <div>
                        <h2 id="mailing-settings-title">Кому отправить</h2>
                        <p>Сначала выберите канал, затем аудиторию.</p>
                    </div>
                </header>

                <?= $form->field($model, 'bot_id', ['options' => ['class' => 'mailing-field mailing-channel-field']])
                    ->radioList($botLabels, [
                        'class' => 'mailing-channel-options',
                        'item' => static function ($index, $label, $name, $checked, $value) {
                            $icon = (int)$value === TelegramConstructor::VK_GROUP ? 'fa-brands fa-vk' : 'fa-brands fa-telegram';
                            $description = (int)$value === TelegramConstructor::VK_GROUP ? 'Сообщения сообщества' : 'Личные сообщения бота';
                            return Html::tag('label',
                                Html::radio($name, $checked, ['value' => $value]) .
                                Html::tag('span', Html::tag('i', '', ['class' => $icon, 'aria-hidden' => 'true']), ['class' => 'mailing-channel-option__icon']) .
                                Html::tag('span', Html::tag('strong', Html::encode($label)) . Html::tag('small', $description), ['class' => 'mailing-channel-option__copy']) .
                                Html::tag('span', Html::tag('i', '', ['class' => 'fa-solid fa-check', 'aria-hidden' => 'true']), ['class' => 'mailing-channel-option__check']),
                                ['class' => 'mailing-channel-option']
                            );
                        },
                    ])->label('Канал') ?>

                <div class="mailing-form-grid mailing-form-grid--audience">
                    <?= $form->field($model, 'audience_id', ['options' => ['class' => 'mailing-field']])
                        ->dropDownList($audienceLabels, ['prompt' => 'Выберите аудиторию', 'class' => 'ds-select form-control'])
                        ->label('Аудитория') ?>
                    <div class="mailing-vk-option" id="mailing-vk-option" hidden>
                        <?= $form->field($model, 'only_with_user', ['options' => ['class' => 'mailing-field mailing-checkbox-field']])
                            ->checkbox(['class' => 'form-check-input'])
                            ->label('Только с аккаунтом на сайте')
                            ->hint('Исключить участников VK без связанного профиля.') ?>
                    </div>
                </div>

                <div class="mailing-audience-result" id="mailing-audience-result" role="status" aria-live="polite">
                    <span class="mailing-audience-result__icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                    <span class="mailing-audience-result__text">Выберите аудиторию — здесь появится точное количество.</span>
                    <a class="mailing-audience-result__link" id="mailing-audience-preview-link" href="#" target="_blank" rel="noopener" hidden>Открыть список <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                </div>
                <?php if (Yii::$app->user->can(Role::ROLE_ADMIN)): ?>
                    <p class="mailing-inline-help">Нет подходящей выборки? <?= Html::a('Создать аудиторию', ['/telegram-recipients/create']) ?>.</p>
                <?php endif; ?>

                <div class="mailing-compose-divider" role="presentation"></div>

                <header class="mailing-compose-sheet__head">
                    <div>
                        <h2>Что отправить</h2>
                        <p>Шаблон можно открыть в новой вкладке, не теряя этот черновик.</p>
                    </div>
                </header>
                <?= $form->field($model, 'telegram_constructor_message_id', ['options' => ['class' => 'mailing-field']])
                    ->dropDownList($messageLabels, ['prompt' => 'Выберите шаблон сообщения', 'class' => 'ds-select form-control'])
                    ->label('Шаблон') ?>
                <div class="mailing-inline-actions">
                    <?= Html::a('<i class="fa-solid fa-plus" aria-hidden="true"></i> Новый шаблон', ['/telegram-constructor-message/create', 'use' => 'campaign'], ['class' => 'mailing-quiet-link', 'target' => '_blank', 'rel' => 'noopener']) ?>
                    <a class="mailing-quiet-link" id="mailing-selected-template-link" href="#" target="_blank" rel="noopener" hidden><i class="fa-solid fa-pen" aria-hidden="true"></i> Изменить выбранный</a>
                </div>

                <div class="mailing-compose-divider" role="presentation"></div>

                <?= $form->field($model, 'title', ['options' => ['class' => 'mailing-field mailing-title-field']])
                    ->textInput(['class' => 'ds-input form-control', 'placeholder' => 'Можно оставить пустым', 'maxlength' => true])
                    ->label('Название в истории <span>необязательно</span>', ['encode' => false])
                    ->hint('Если не заполнить, название составится из шаблона и даты.') ?>
            </section>

            <div class="mailing-form-actions mailing-form-actions--simple">
                <?= Html::submitButton('Перейти к проверке <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>', ['class' => 'ds-btn ds-btn--primary']) ?>
                <?= Html::a('Отмена', $this->context->getIndexUrl(), ['class' => 'ds-btn ds-btn--secondary']) ?>
                <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Ничего не отправится без отдельного подтверждения</span>
            </div>
        </main>

        <aside class="mailing-compose__aside" aria-labelledby="mailing-preview-title">
            <div class="mailing-preview-sticky">
                <header>
                    <div><span>Предпросмотр</span><h2 id="mailing-preview-title">Сообщение</h2></div>
                    <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </header>
                <dl class="mailing-compose-summary" aria-label="Параметры рассылки">
                    <div><dt>Канал</dt><dd id="mailing-summary-channel">—</dd></div>
                    <div><dt>Получатели</dt><dd id="mailing-summary-audience">—</dd></div>
                    <div><dt>Шаблон</dt><dd id="mailing-summary-template">—</dd></div>
                </dl>
                <div class="constructor_message_preview mailing-message-preview" id="mailing-message-preview" aria-live="polite">
                    <div class="mailing-preview-empty"><i class="fa-regular fa-message" aria-hidden="true"></i><span>Выберите шаблон, чтобы увидеть сообщение.</span></div>
                </div>
                <p class="mailing-preview-note">Персональные ссылки и данные подставятся при отправке.</p>
            </div>
        </aside>
    <?php ActiveForm::end() ?>
</div>
<?php
$urls = Json::htmlEncode([
    'templateEdit' => Url::to(['/telegram-constructor-message/update', 'id' => '__id__']),
]);
$this->registerJs("window.mailingComposerConfig = {$urls};", yii\web\View::POS_HEAD);
?>
