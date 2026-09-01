<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use backend\models\TelegramConstructorMessage;

/* @var $this yii\web\View */
/* @var $model backend\models\TelegramConstructorMessage */
/* @var $form yii\widgets\ActiveForm */

$languages = [
    ['language_id' => 'ru-RU', 'language' => 'ru'],
];
\lajax\languagepicker\bundles\LanguageLargeIconsAsset::register($this);

$labelOptions = ['class' => 'text-xs text-zinc-400 mb-1 block'];
$sectionClass = 'bg-[hsl(0_0%_11.8%_/_1)] overflow-hidden rounded-lg border border-[hsl(0_0%_15.3%_/_1)]';
$borderDivider = 'border-[hsl(0_0%_15.3%_/_1)]';
?>
<div class="tcm-form-wrap p-4 lg:p-6">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'tcm-form']]); ?>

    <div class="<?= $sectionClass ?> mb-4">
        <div class="p-4 border-b <?= $borderDivider ?>">
            <label class="<?= $labelOptions['class'] ?>"><?= Yii::t('common', 'Название') ?></label>
            <?= $form->field($model, 'title', [
                'labelOptions' => ['class' => 'hidden'],
                'template' => '{input}{error}',
            ])->textInput(['class' => 'ds-input w-full']) ?>
        </div>
    </div>

    <ul class="tcm-form-tabs flex flex-wrap gap-1 mb-4 nav nav-pills language-picker large">
        <?php foreach ($languages as $language): ?>
            <li class="tabs_tg_message"><a href="#preview_message_<?= $language['language_id'] ?>" data-language="<?= $language['language_id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded text-sm font-medium bg-[hsl(0_0%_18%_/_1)] text-zinc-300 border border-[hsl(0_0%_22%_/_1)] hover:bg-[hsl(0_0%_22%_/_1)] no-underline"><i class="<?= $language['language_id'] ?>"></i> <?= strtoupper($language['language']) ?></a></li>
        <?php endforeach; ?>
    </ul>

    <div class="tg-preview_wrap <?= $sectionClass ?>">
        <div class="tg-preview p-4">
            <?php foreach ($languages as $language): ?>
                <?php $language_id = $language['language_id']; ?>
                <div class="tg-preview_message" id="preview_message_<?= $language['language_id'] ?>">
                    <?php
                    $imageLink = $model->getImageLink($language_id);
                    $isUrl = !empty($imageLink) && strpos($imageLink, '@') === 0;
                    $imageUrlValue = $isUrl ? substr($imageLink, 1) : '';
                    ?>
                    <div class="mb-4">
                        <label class="<?= $labelOptions['class'] ?>"><?= Yii::t('common', 'Ссылка на изображение (начинается с @, можно использовать {user_id}):') ?></label>
                        <?= Html::textInput("TelegramConstructorMessageForm[image_url][{$language_id}]", $imageUrlValue, [
                            'class' => 'ds-input w-full',
                            'placeholder' => '@https://prostoj.store/year-review/generate?userId={user_id}',
                        ]) ?>
                        <p class="text-xs text-zinc-500 mt-1"><?= Yii::t('common', 'Если указана ссылка, загрузка файла будет проигнорирована. Используйте {user_id} для подстановки ID пользователя.') ?></p>
                    </div>
                    <?= $form->field($model, "image_file[{$language['language_id']}]", [
                        'template' => '{label}{input}{error}',
                        'labelOptions' => $labelOptions,
                    ])->label(Yii::t('common', 'Или загрузите файл:'))->widget(\kartik\file\FileInput::class, [
                        'options' => ['accept' => 'image/*'],
                        'pluginOptions' => [
                            'showCaption' => true,
                            'showRemove' => false,
                            'showUpload' => false,
                            'overwriteInitial' => true,
                            'initialPreviewAsData' => false,
                            'initialPreviewFileType' => 'image',
                            'initialPreview' => !$isUrl && $imageLink ? [
                                "<img src=\"{$model->getPubUrl('', $language_id)}\" class=\"file-preview-image\" alt=\"Image\" title=\"Image\">"
                            ] : false,
                        ],
                    ]) ?>
                    <div class="tcm-tinymce-wrap blog-form-tinymce-wrap mt-4">
                        <label class="<?= $labelOptions['class'] ?>"><?= Yii::t('common', 'Текст сообщения') ?></label>
                        <?= $form->field($model, "message[{$language_id}]", ['options' => ['class' => 'mb-0'], 'template' => '{input}{error}', 'labelOptions' => ['class' => 'hidden']])->widget(\dosamigos\tinymce\TinyMce::class, [
                            'options' => ['rows' => 6, 'value' => $model->getMessage($language_id)],
                            'language' => 'ru',
                            'clientOptions' => [
                                'license_key' => 'gpl',
                                'skin' => 'oxide-dark',
                                'content_css' => 'dark',
                                'content_style' => 'body { background-color: hsl(0,0%,13%); color: #e5e5e5; font-size: 14px; line-height: 1.5; }',
                                'plugins' => ['emoticons', 'link', 'code'],
                                'menubar' => false,
                                'resize' => false,
                                'statusbar' => false,
                                'paste_as_text' => true,
                                'toolbar' => 'undo redo bold italic emoticons link | code',
                                'default_link_target' => '_blank',
                                'link_context_toolbar' => true,
                                'height' => 220,
                            ],
                        ]) ?>
                    </div>
                    <?= $form->field($model, "is_delete_image[{$language['language_id']}]")->label(false)->hiddenInput(['value' => '0', 'class' => 'is_delete_image']) ?>
                </div>
            <?php endforeach; ?>
            <div class="telegram_message_buttons_wrap mt-4 pt-4 border-t <?= $borderDivider ?>">
                <div class="telegram_message_buttons" id="sortable-buttons">
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
            </div>
        </div>
        <div class="tg-preview_bg"></div>
    </div>

    <div class="flex flex-wrap gap-2 items-center mt-4">
        <?= Html::submitButton('<i class="fas fa-save"></i> ' . Yii::t('common', 'Сохранить'), ['class' => 'ds-btn ds-btn--primary']) ?>
        <?= Html::a('<i class="fas fa-arrow-left"></i> ' . Yii::t('common', 'Отмена'), ['index'], ['class' => 'ds-btn ds-btn--secondary']) ?>
    </div>

    <div class="modal fade" id="modalFormAddButtonTgConstructor" tabindex="-1" role="dialog" aria-labelledby="modalForm">
        <div class="modal-dialog" role="document">
            <div class="modal-content bg-[hsl(0_0%_11.8%_/_1)] border border-[hsl(0_0%_15.3%_/_1)] rounded-lg overflow-hidden">
                <div class="modal-header flex items-center justify-between p-4 border-b border-[hsl(0_0%_15.3%_/_1)]">
                    <h5 class="text-sm font-semibold text-white uppercase tracking-wide m-0"><?= Yii::t('common', 'Добавить кнопку') ?></h5>
                    <button type="button" class="ds-btn ds-btn--icon ds-btn--ghost close" data-bs-dismiss="modal" aria-label="Закрыть окно"><i class="fas fa-times" aria-hidden="true"></i></button>
                </div>
                <div class="modal-body p-4">
                    <?php foreach ($languages as $language): ?>
                        <div class="mb-4">
                            <label class="<?= $labelOptions['class'] ?>"><i class="<?= $language['language_id'] ?>"></i> <?= strtoupper($language['language']) ?></label>
                            <input type="text" class="ds-input w-full telegramConstructorMessageButtonTitle" data-language="<?= $language['language_id'] ?>" value=""/>
                        </div>
                    <?php endforeach; ?>
                    <div class="mb-4">
                        <label class="<?= $labelOptions['class'] ?>"><?= Yii::t('common', 'Перейти по внешней ссылке:') ?></label>
                        <input type="text" class="ds-input w-full" id="telegramConstructorMessageButtonUrl" value=""/>
                    </div>
                    <div class="mb-4">
                        <label class="<?= $labelOptions['class'] ?>"><?= Yii::t('common', 'Отправить ответное сообщение:') ?></label>
                        <?= $form->field($model, 'buttonResponseMessageId', ['template' => '{input}', 'labelOptions' => ['class' => 'hidden']])->widget(\kartik\select2\Select2::class, [
                            'data' => \yii\helpers\ArrayHelper::merge(['0' => Yii::t('common', 'Выберите сообщение...')], TelegramConstructorMessage::getList()),
                            'options' => ['id' => 'telegramConstructorMessageButtonMessageId', 'prompt' => Yii::t('common', 'Выберите сообщение...')],
                            'showToggleAll' => false,
                            'pluginOptions' => ['width' => '100%'],
                        ]) ?>
                    </div>
                </div>
                <div class="modal-footer flex flex-wrap gap-2 p-4 border-t border-[hsl(0_0%_15.3%_/_1)]">
                    <button type="button" class="ds-btn ds-btn--primary addButton" data-bs-dismiss="modal"><i class="fas fa-check" aria-hidden="true"></i> <?= Yii::t('common', 'Сохранить') ?></button>
                    <button type="button" class="ds-btn ds-btn--secondary" data-bs-dismiss="modal"><i class="fas fa-times" aria-hidden="true"></i> <?= Yii::t('common', 'Отмена') ?></button>
                </div>
            </div>
        </div>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<?php
$languagesStr = json_encode($languages);
$this->registerJs(<<<JS
    var languages = {$languagesStr};
JS
, \yii\web\View::POS_HEAD);
?>
