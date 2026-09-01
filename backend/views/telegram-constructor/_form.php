<?php

use backend\models\TelegramConstructor;
use backend\models\TelegramConstructorMessage;
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
            <span class="mailing-page-head__eyebrow">Черновик</span>
            <h1><?= Html::encode($formTitle) ?></h1>
            <p>Заполните три блока. После сохранения откроется итоговая проверка перед отправкой.</p>
        </div>
    </header>

    <?php $form = ActiveForm::begin([
        'id' => 'telegram-constructor-form',
        'options' => [
            'class' => 'mailing-compose',
            'data-audience-count-url' => $countUrl,
            'data-audience-preview-url' => $audienceUrl,
            'data-message-preview-url' => $messageUrl,
            'data-vk-bot-id' => TelegramConstructor::VK_GROUP,
        ],
    ]) ?>
        <main class="mailing-compose__main">
            <?= Html::errorSummary($model, [
                'class' => 'ds-alert ds-alert--danger mailing-form-errors',
                'header' => '<strong>Не удалось сохранить черновик</strong><span>Исправьте поля ниже.</span>',
                'encode' => false,
            ]) ?>

            <section class="mailing-form-section" aria-labelledby="mailing-step-details">
                <header class="mailing-form-section__head">
                    <span class="mailing-step-number">1</span>
                    <div><h2 id="mailing-step-details">Основное</h2><p>Внутреннее название, понятное команде.</p></div>
                </header>
                <?= $form->field($model, 'title', ['options' => ['class' => 'mailing-field']])
                    ->textInput(['class' => 'ds-input form-control', 'placeholder' => 'Например: Анонс сентябрьского вайпа', 'maxlength' => true])
                    ->label('Название рассылки')
                    ->hint('Получатели это название не увидят.') ?>
            </section>

            <section class="mailing-form-section" aria-labelledby="mailing-step-audience">
                <header class="mailing-form-section__head">
                    <span class="mailing-step-number">2</span>
                    <div><h2 id="mailing-step-audience">Канал и аудитория</h2><p>Выберите, куда и кому уйдёт сообщение.</p></div>
                </header>
                <div class="mailing-form-grid">
                    <?= $form->field($model, 'bot_id', ['options' => ['class' => 'mailing-field']])
                        ->dropDownList($botLabels, ['prompt' => 'Выберите канал', 'class' => 'ds-select form-control'])
                        ->label('Канал отправки') ?>
                    <?= $form->field($model, 'audience_id', ['options' => ['class' => 'mailing-field']])
                        ->dropDownList($audienceLabels, ['prompt' => 'Выберите аудиторию', 'class' => 'ds-select form-control'])
                        ->label('Аудитория') ?>
                </div>
                <div class="mailing-vk-option" id="mailing-vk-option">
                    <?= $form->field($model, 'only_with_user', ['options' => ['class' => 'mailing-field mailing-checkbox-field']])
                        ->checkbox(['class' => 'form-check-input'])
                        ->label('Только участники VK с аккаунтом на сайте')
                        ->hint('Полезно, если в сообщении используются персональные данные или ссылки.') ?>
                </div>
                <div class="mailing-audience-result" id="mailing-audience-result" role="status" aria-live="polite">
                    <span class="mailing-audience-result__icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                    <span class="mailing-audience-result__text">Выберите канал и аудиторию — здесь появится точное количество.</span>
                    <a class="mailing-audience-result__link" id="mailing-audience-preview-link" href="#" target="_blank" rel="noopener" hidden>Посмотреть список <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                </div>
                <?php if (Yii::$app->user->can(\common\components\helpers\Role::ROLE_ADMIN)): ?>
                    <p class="mailing-inline-help">Нужна отдельная выборка? <?= Html::a('Создайте сохранённую аудиторию', ['/telegram-recipients/create']) ?>.</p>
                <?php endif; ?>
            </section>

            <section class="mailing-form-section" aria-labelledby="mailing-step-message">
                <header class="mailing-form-section__head">
                    <span class="mailing-step-number">3</span>
                    <div><h2 id="mailing-step-message">Сообщение</h2><p>Выберите заранее подготовленный шаблон.</p></div>
                </header>
                <?= $form->field($model, 'telegram_constructor_message_id', ['options' => ['class' => 'mailing-field']])
                    ->dropDownList($messageLabels, ['prompt' => 'Выберите шаблон', 'class' => 'ds-select form-control'])
                    ->label('Шаблон сообщения') ?>
                <div class="mailing-inline-actions">
                    <?= Html::a('<i class="fa-solid fa-plus" aria-hidden="true"></i> Новый шаблон', ['/telegram-constructor-message/create'], ['class' => 'mailing-quiet-link']) ?>
                    <a class="mailing-quiet-link" id="mailing-selected-template-link" href="#" hidden><i class="fa-solid fa-pen" aria-hidden="true"></i> Открыть шаблон</a>
                </div>
            </section>

            <div class="mailing-form-actions">
                <?= Html::submitButton('<i class="fa-solid fa-arrow-right" aria-hidden="true"></i> Сохранить и проверить', ['class' => 'ds-btn ds-btn--primary']) ?>
                <?= Html::a('Отмена', $this->context->getIndexUrl(), ['class' => 'ds-btn ds-btn--secondary']) ?>
                <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Отправка не начнётся после сохранения</span>
            </div>
        </main>

        <aside class="mailing-compose__aside" aria-labelledby="mailing-preview-title">
            <div class="mailing-preview-sticky">
                <header>
                    <div><span>Предпросмотр</span><h2 id="mailing-preview-title">Сообщение получателя</h2></div>
                    <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </header>
                <div class="constructor_message_preview mailing-message-preview" id="mailing-message-preview" aria-live="polite">
                    <div class="mailing-preview-empty"><i class="fa-regular fa-message" aria-hidden="true"></i><span>Выберите шаблон, чтобы увидеть сообщение.</span></div>
                </div>
                <p class="mailing-preview-note">Это пример. Персональные ссылки и данные подставятся при отправке.</p>
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
