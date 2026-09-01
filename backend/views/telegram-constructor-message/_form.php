<?php

use backend\forms\TelegramConstructorMessageForm;
use backend\models\TelegramConstructorMessage;
use kartik\file\FileInput;
use kartik\select2\Select2;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var TelegramConstructorMessageForm $model */

$languageId = 'ru-RU';
$languages = [['language_id' => $languageId, 'language' => 'ru']];
$imageLink = $model->getImageLink($languageId);
$isUrl = $imageLink && strpos($imageLink, '@') === 0;
$imageUrlValue = $isUrl ? substr($imageLink, 1) : '';
?>
<div class="mailing-page mailing-template-form-page">
    <?= $this->render('@backend/views/telegram-constructor/_section_nav') ?>

    <header class="mailing-page-head mailing-page-head--compact">
        <div>
            <span class="mailing-page-head__eyebrow">Шаблон</span>
            <h1><?= $model->isNewRecord ? 'Новый шаблон' : 'Редактирование шаблона' ?></h1>
            <p>Соберите содержимое сообщения. Перед запуском его можно ещё раз проверить в черновике рассылки.</p>
        </div>
    </header>

    <?php $form = ActiveForm::begin(['options' => ['class' => 'mailing-template-form', 'enctype' => 'multipart/form-data']]) ?>
        <main class="mailing-template-form__main">
            <?= Html::errorSummary($model, ['class' => 'ds-alert ds-alert--danger mailing-form-errors', 'header' => '<strong>Проверьте шаблон</strong>', 'encode' => false]) ?>

            <section class="mailing-form-section">
                <header class="mailing-form-section__head">
                    <span class="mailing-step-number">1</span>
                    <div><h2>Название</h2><p>Только для поиска шаблона в админке.</p></div>
                </header>
                <?= $form->field($model, 'title', ['options' => ['class' => 'mailing-field']])->textInput(['class' => 'ds-input form-control', 'maxlength' => true, 'placeholder' => 'Например: Анонс вайпа'])->label('Название шаблона') ?>
            </section>

            <section class="mailing-form-section">
                <header class="mailing-form-section__head">
                    <span class="mailing-step-number">2</span>
                    <div><h2>Содержимое</h2><p>Добавьте текст, изображение или оба элемента.</p></div>
                </header>

                <div class="mailing-field">
                    <label for="telegram-message-image-url">Ссылка на изображение</label>
                    <?= Html::textInput("TelegramConstructorMessageForm[image_url][{$languageId}]", $imageUrlValue, ['class' => 'ds-input form-control', 'id' => 'telegram-message-image-url', 'placeholder' => 'https://example.com/image.jpg']) ?>
                    <div class="mailing-field-note">Можно использовать <code>{user_id}</code> для персональной ссылки. Ссылка имеет приоритет над файлом.</div>
                </div>

                <?= $form->field($model, "image_file[{$languageId}]", ['options' => ['class' => 'mailing-field']])->widget(FileInput::class, [
                    'options' => ['accept' => 'image/png,image/jpeg,image/gif,image/webp'],
                    'pluginOptions' => [
                        'showCaption' => true,
                        'showRemove' => true,
                        'showUpload' => false,
                        'browseLabel' => 'Выбрать файл',
                        'removeLabel' => 'Убрать',
                        'initialPreviewAsData' => true,
                        'initialPreview' => !$isUrl && $imageLink ? [$model->getPubUrl('', $languageId)] : [],
                    ],
                ])->label('Или загрузите изображение')->hint('PNG, JPG, GIF или WebP, до 3 МБ.') ?>

                <div class="mailing-field mailing-editor-field">
                    <label for="telegram-message-editor">Текст сообщения</label>
                    <?= $form->field($model, "message[{$languageId}]", ['template' => '{input}{error}', 'options' => ['class' => '']])->widget(\dosamigos\tinymce\TinyMce::class, [
                        'options' => ['rows' => 8, 'value' => $model->getMessage($languageId), 'id' => 'telegram-message-editor'],
                        'language' => 'ru',
                        'clientOptions' => [
                            'license_key' => 'gpl',
                            'skin' => 'oxide-dark',
                            'content_css' => 'dark',
                            'content_style' => 'body { background: #15181d; color: #f5f7fa; font: 14px/1.55 Inter, sans-serif; padding: 12px; } a { color: #9bc2ff; }',
                            'plugins' => ['emoticons', 'link', 'code', 'lists'],
                            'menubar' => false,
                            'resize' => true,
                            'statusbar' => true,
                            'paste_as_text' => true,
                            'toolbar' => 'undo redo | bold italic | bullist numlist | emoticons link | code',
                            'default_link_target' => '_blank',
                            'height' => 300,
                        ],
                    ]) ?>
                    <div class="mailing-field-note">Используйте короткие абзацы. Сложная HTML-разметка может отличаться между Telegram и VK.</div>
                </div>
                <?= $form->field($model, "is_delete_image[{$languageId}]")->label(false)->hiddenInput(['value' => '0', 'class' => 'is_delete_image']) ?>
            </section>

            <section class="mailing-form-section">
                <header class="mailing-form-section__head mailing-form-section__head--actions">
                    <span class="mailing-step-number">3</span>
                    <div><h2>Кнопки</h2><p>Необязательно. У каждой кнопки должно быть одно действие.</p></div>
                    <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm button_add" data-bs-toggle="modal" data-bs-target="#modalFormAddButtonTgConstructor"><i class="fa-solid fa-plus" aria-hidden="true"></i> Добавить кнопку</button>
                </header>
                <div class="telegram_message_buttons" id="sortable-buttons" aria-live="polite">
                    <?php foreach ($model->telegramConstructorButtons as $i => $button): ?>
                        <?= $this->render('button', [
                            'messageId' => $button->callback_telegram_constructor_message_id,
                            'url' => $button->url,
                            'languages' => $languages,
                            'titles' => $button->getButonsText(),
                            'index' => $i + 1,
                        ]) ?>
                    <?php endforeach; ?>
                </div>
                <div class="mailing-empty-buttons">Кнопок нет. Сообщение можно сохранить без них.</div>
            </section>

            <div class="mailing-form-actions">
                <?= Html::submitButton('<i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Сохранить шаблон', ['class' => 'ds-btn ds-btn--primary']) ?>
                <?= Html::a('Отмена', ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
            </div>
        </main>

        <aside class="mailing-template-form__aside">
            <div class="mailing-template-guidance">
                <h2>Перед сохранением</h2>
                <ul>
                    <li>Главная мысль видна в первых двух строках.</li>
                    <li>Ссылка ведёт на HTTPS-страницу.</li>
                    <li>У кнопок короткие и разные названия.</li>
                    <li>Изображение читается на экране телефона.</li>
                </ul>
                <?php if (!$model->isNewRecord): ?>
                    <div class="mailing-template-current-preview">
                        <span>Текущая сохранённая версия</span>
                        <?= $this->render('preview', ['model' => $model]) ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <div class="modal fade" id="modalFormAddButtonTgConstructor" tabindex="-1" aria-labelledby="mailing-button-modal-title" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <header class="modal-header">
                        <h2 class="modal-title" id="mailing-button-modal-title">Кнопка сообщения</h2>
                        <button type="button" class="ds-btn ds-btn--icon ds-btn--ghost" data-bs-dismiss="modal" aria-label="Закрыть"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </header>
                    <div class="modal-body">
                        <div class="mailing-button-error ds-alert ds-alert--danger" role="alert" hidden></div>
                        <div class="mailing-field">
                            <label for="mailing-button-title">Название кнопки</label>
                            <input type="text" id="mailing-button-title" class="ds-input form-control telegramConstructorMessageButtonTitle" data-language="<?= $languageId ?>" maxlength="64" placeholder="Например: Подробнее">
                        </div>
                        <fieldset class="mailing-button-destination">
                            <legend>Действие после нажатия</legend>
                            <div class="mailing-field">
                                <label for="telegramConstructorMessageButtonUrl">Открыть ссылку</label>
                                <input type="url" class="ds-input form-control" id="telegramConstructorMessageButtonUrl" placeholder="https://example.com/page">
                            </div>
                            <div class="mailing-choice-divider"><span>или</span></div>
                            <?= $form->field($model, 'buttonResponseMessageId', ['options' => ['class' => 'mailing-field']])->widget(Select2::class, [
                                'data' => TelegramConstructorMessage::getList(),
                                'options' => ['id' => 'telegramConstructorMessageButtonMessageId', 'placeholder' => 'Отправить другой шаблон'],
                                'showToggleAll' => false,
                                'pluginOptions' => ['allowClear' => true, 'width' => '100%'],
                            ])->label('Отправить ответное сообщение') ?>
                        </fieldset>
                    </div>
                    <footer class="modal-footer">
                        <button type="button" class="ds-btn ds-btn--secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="button" class="ds-btn ds-btn--primary addButton"><i class="fa-solid fa-check" aria-hidden="true"></i> Сохранить кнопку</button>
                    </footer>
                </div>
            </div>
        </div>
    <?php ActiveForm::end() ?>
</div>
<?php
$languagesJson = \yii\helpers\Json::htmlEncode($languages);
$this->registerJs("window.languages = {$languagesJson};", yii\web\View::POS_HEAD);
?>
