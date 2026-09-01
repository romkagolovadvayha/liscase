<?php

use backend\forms\TelegramConstructorMessageForm;
use backend\models\TelegramConstructorMessage;
use kartik\file\FileInput;
use kartik\select2\Select2;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\JsExpression;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var TelegramConstructorMessageForm $model */

$languageId = 'ru-RU';
$languages = [['language_id' => $languageId, 'language' => 'ru']];
$imageLink = $model->getImageLink($languageId);
$isUrl = $imageLink && strpos($imageLink, '@') === 0;
$loadedImageUrls = is_array($model->image_url) ? $model->image_url : [];
$imageUrlValue = array_key_exists($languageId, $loadedImageUrls)
    ? (string)$loadedImageUrls[$languageId]
    : ($isUrl ? substr($imageLink, 1) : '');
$imageMode = $model->image_mode;
if (!in_array($imageMode, ['none', 'url', 'upload'], true)) {
    $imageMode = $isUrl ? 'url' : ($imageLink ? 'upload' : 'none');
    $model->image_mode = $imageMode;
}
$deleteImage = $imageMode === 'none' ? 1 : 0;
$loadedMessages = is_array($model->message) ? $model->message : [];
$messageValue = array_key_exists($languageId, $loadedMessages)
    ? (string)$loadedMessages[$languageId]
    : (string)$model->getMessage($languageId);
$previewImage = $imageMode !== 'none' && $imageLink ? $model->getPubUrl('', $languageId) : '';
$buttonRows = [];
if (is_array($model->buttons)) {
    foreach ($model->buttons as $button) {
        $titles = [];
        foreach ((array)($button['title'] ?? []) as $language => $title) {
            $titles[] = ['text' => (string)$title, 'language' => (string)$language];
        }
        $buttonRows[] = [
            'messageId' => $button['message'] ?? null,
            'url' => $button['url'] ?? null,
            'titles' => $titles,
            'previewTitle' => (string)($button['title'][$languageId] ?? ''),
        ];
    }
} else {
    foreach ($model->telegramConstructorButtons as $button) {
        $buttonRows[] = [
            'messageId' => $button->callback_telegram_constructor_message_id,
            'url' => $button->url,
            'titles' => $button->getButonsText(),
            'previewTitle' => (string)$button->getText($languageId),
        ];
    }
}
$hasButtons = $buttonRows !== [];
?>
<div class="mailing-page mailing-template-form-page">
    <?= $this->render('@backend/views/telegram-constructor/_section_nav') ?>

    <header class="mailing-page-head mailing-page-head--compact">
        <div>
            <span class="mailing-page-head__eyebrow">Шаблон сообщения</span>
            <h1><?= $model->isNewRecord ? 'Новый шаблон' : 'Редактирование шаблона' ?></h1>
            <p>Напишите текст, при необходимости добавьте изображение и кнопки. Изменения видны в предпросмотре справа.</p>
        </div>
    </header>

    <?php $form = ActiveForm::begin([
        'id' => 'mailing-template-form',
        'options' => ['class' => 'mailing-template-form mailing-template-form--simple', 'enctype' => 'multipart/form-data'],
    ]) ?>
        <main class="mailing-template-form__main">
            <?= Html::errorSummary($model, [
                'class' => 'ds-alert ds-alert--danger mailing-form-errors',
                'header' => '<strong>Не удалось сохранить шаблон</strong><span>Исправьте отмеченные поля.</span>',
                'encode' => false,
            ]) ?>

            <section class="mailing-compose-sheet" aria-labelledby="mailing-template-content-title">
                <header class="mailing-compose-sheet__head">
                    <div>
                        <h2 id="mailing-template-content-title">Сообщение</h2>
                        <p>Название видит только команда, получатели увидят содержимое ниже.</p>
                    </div>
                </header>

                <?= $form->field($model, 'title', ['options' => ['class' => 'mailing-field']])
                    ->textInput(['class' => 'ds-input form-control', 'maxlength' => true, 'placeholder' => 'Например: Анонс нового вайпа'])
                    ->label('Название шаблона') ?>

                <div class="mailing-field mailing-editor-field">
                    <div class="mailing-field-label-row">
                        <label for="telegram-message-editor">Текст</label>
                        <span class="mailing-character-count" id="mailing-message-character-count">0 / 4096</span>
                    </div>
                    <?= $form->field($model, "message[{$languageId}]", ['template' => '{input}{error}', 'options' => ['class' => '']])->widget(\dosamigos\tinymce\TinyMce::class, [
                        'options' => ['rows' => 8, 'value' => $messageValue, 'id' => 'telegram-message-editor'],
                        'language' => 'ru',
                        'clientOptions' => [
                            'license_key' => 'gpl',
                            'skin' => 'oxide-dark',
                            'content_css' => 'dark',
                            'content_style' => 'body { background: #15181d; color: #f5f7fa; font: 14px/1.55 Inter, sans-serif; padding: 12px; } a { color: #9bc2ff; }',
                            'plugins' => ['emoticons', 'link', 'code', 'lists'],
                            'menubar' => false,
                            'resize' => true,
                            'statusbar' => false,
                            'paste_as_text' => true,
                            'toolbar' => 'undo redo | bold italic | bullist numlist | emoticons link | code',
                            'default_link_target' => '_blank',
                            'height' => 260,
                            'setup' => new JsExpression("function (editor) { window.mailingTemplateEditor = editor; editor.on('init change input keyup undo redo', function () { document.dispatchEvent(new CustomEvent('mailing:editor-change')); }); }"),
                        ],
                    ]) ?>
                    <div class="mailing-field-note">До 4096 символов. Если подпись длиннее 1024 символов, Telegram получит изображение и текст двумя сообщениями.</div>
                </div>

                <div class="mailing-compose-divider" role="presentation"></div>

                <?= $form->field($model, 'image_mode', ['options' => ['class' => 'mailing-field mailing-media-field']])
                    ->radioList([
                        'none' => ['label' => 'Без изображения', 'icon' => 'fa-regular fa-image'],
                        'url' => ['label' => 'По ссылке', 'icon' => 'fa-solid fa-link'],
                        'upload' => ['label' => 'Загрузить файл', 'icon' => 'fa-solid fa-arrow-up-from-bracket'],
                    ], [
                        'class' => 'mailing-media-options',
                        'item' => static function ($index, $option, $name, $checked, $value) {
                            return Html::tag('label',
                                Html::radio($name, $checked, ['value' => $value]) .
                                Html::tag('i', '', ['class' => $option['icon'], 'aria-hidden' => 'true']) .
                                Html::tag('span', Html::encode($option['label'])),
                                ['class' => 'mailing-media-option']
                            );
                        },
                    ])->label('Изображение <span>необязательно</span>', ['encode' => false]) ?>

                <div class="mailing-media-panel" data-mailing-media-panel="url"<?= $imageMode === 'url' ? '' : ' hidden' ?>>
                    <div class="mailing-field">
                        <label for="telegram-message-image-url">Ссылка на изображение</label>
                        <?= Html::textInput("TelegramConstructorMessageForm[image_url][{$languageId}]", $imageUrlValue, [
                            'class' => 'ds-input form-control',
                            'id' => 'telegram-message-image-url',
                            'placeholder' => 'https://example.com/image.jpg',
                            'maxlength' => 254,
                        ]) ?>
                        <?= Html::error($model, 'image_url', ['class' => 'invalid-feedback d-block']) ?>
                        <div class="mailing-field-note">Можно использовать <code>{user_id}</code> в персональной ссылке.</div>
                    </div>
                </div>

                <div class="mailing-media-panel" data-mailing-media-panel="upload"<?= $imageMode === 'upload' ? '' : ' hidden' ?>>
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
                    ])->label(false)->hint('PNG, JPG, GIF или WebP, до 3 МБ.') ?>
                </div>
                <?= $form->field($model, "is_delete_image[{$languageId}]")->label(false)->hiddenInput(['value' => $deleteImage, 'class' => 'is_delete_image']) ?>

                <details class="mailing-optional-section"<?= $hasButtons ? ' open' : '' ?>>
                    <summary>
                        <span><i class="fa-solid fa-table-cells-large" aria-hidden="true"></i><strong>Кнопки</strong><small>Необязательно<?= $hasButtons ? ' · ' . count($buttonRows) : '' ?></small></span>
                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                    </summary>
                    <div class="mailing-optional-section__content">
                        <div class="mailing-optional-section__head">
                            <p>Добавляйте кнопку только когда получателю действительно нужно отдельное действие.</p>
                            <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm button_add" data-bs-toggle="modal" data-bs-target="#modalFormAddButtonTgConstructor"><i class="fa-solid fa-plus" aria-hidden="true"></i> Добавить кнопку</button>
                        </div>
                        <div class="telegram_message_buttons" id="sortable-buttons" aria-live="polite">
                            <?php foreach ($buttonRows as $i => $button): ?>
                                <?= $this->render('button', [
                                    'messageId' => $button['messageId'],
                                    'url' => $button['url'],
                                    'languages' => $languages,
                                    'titles' => $button['titles'],
                                    'index' => $i + 1,
                                ]) ?>
                            <?php endforeach; ?>
                        </div>
                        <div class="mailing-empty-buttons">Кнопок пока нет.</div>
                    </div>
                </details>
            </section>

            <div class="mailing-form-actions mailing-form-actions--simple">
                <?= Html::submitButton('<i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Сохранить шаблон', ['class' => 'ds-btn ds-btn--primary']) ?>
                <?= Html::a('Отмена', ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
            </div>
        </main>

        <aside class="mailing-template-form__aside" aria-labelledby="mailing-template-preview-title">
            <div class="mailing-preview-sticky mailing-template-live-preview">
                <header>
                    <div><span>Предпросмотр</span><h2 id="mailing-template-preview-title">Сообщение</h2></div>
                    <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </header>
                <div class="constructor_message_preview">
                    <div class="mailing-message-bubble">
                        <img class="mailing-message-bubble__image" id="mailing-template-preview-image" src="<?= Html::encode($previewImage) ?>" data-upload-src="<?= Html::encode(!$isUrl ? $previewImage : '') ?>" alt="Изображение сообщения"<?= $previewImage ? '' : ' hidden' ?>>
                        <div class="mailing-message-bubble__body" id="mailing-template-preview-text">
                            <?= trim($messageValue) !== '' ? $messageValue : '<span class="mailing-message-bubble__empty">Добавьте текст или изображение.</span>' ?>
                        </div>
                        <div class="mailing-message-bubble__buttons" id="mailing-template-preview-buttons"<?= $hasButtons ? '' : ' hidden' ?>>
                            <?php foreach ($buttonRows as $button): ?>
                                <span><?= Html::encode($button['previewTitle'] ?: 'Кнопка без названия') ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <p class="mailing-preview-note">Предпросмотр приблизительный: Telegram и VK могут немного отличаться.</p>
            </div>
        </aside>

        <div class="modal fade" id="modalFormAddButtonTgConstructor" tabindex="-1" aria-labelledby="mailing-button-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
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
                            <legend>Что произойдёт после нажатия</legend>
                            <div class="mailing-field">
                                <label for="telegramConstructorMessageButtonUrl">Открыть ссылку</label>
                                <input type="url" class="ds-input form-control" id="telegramConstructorMessageButtonUrl" maxlength="255" placeholder="https://example.com/page">
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
$languagesJson = Json::htmlEncode($languages);
$this->registerJs("window.languages = {$languagesJson};", yii\web\View::POS_HEAD);
?>
