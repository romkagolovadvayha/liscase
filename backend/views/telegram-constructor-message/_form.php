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

?>

<div class="ds-card">
    <div class="ds-card__header">
        <h5 class="ds-card__header-title"><?= $model->isNewRecord ? 'Создать сообщение' : 'Редактировать сообщение' ?></h5>
    </div>
    <div class="ds-card__body">
        <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'title')->textInput() ?>

    <ul class="nav nav-pills language-picker large">
    <?php foreach ($languages as $language): ?>
            <li class="tabs_tg_message"><a href="#preview_message_<?=$language['language_id']?>" data-language="<?=$language['language_id']?>"><i class="<?=$language['language_id']?>"></i> <?=strtoupper($language['language'])?></a></li>
    <?php endforeach; ?>
    </ul>
    <div class="tg-preview_wrap">
        <div class="tg-preview">
            <?php foreach ($languages as $language): ?>
            <?php  $language_id = $language['language_id']; ?>
            <div class="tg-preview_message" id="preview_message_<?=$language['language_id']?>">
                <?= $form->field($model, "image_file[{$language['language_id']}]")->label(false)->widget(\kartik\file\FileInput::class, [
                    'options'       => [
                        'accept' => 'image/*',
                    ],
                    'pluginOptions' => [
                        'showCaption'            => true,
                        'showRemove'             => false,
                        'showUpload'             => false,
                        'overwriteInitial'       => true,
                        'initialPreviewAsData'   => false,
                        'initialPreviewFileType' => 'image',
                        'initialPreview'         => $model->getImageLink($language_id) ? [
                            "<img src=\"{$model->getPubUrl('', $language_id)}\" class=\"file-preview-image\" alt=\"Image\" title=\"Image\">"
                        ] : false,
                    ],
                ]);
                ?>
                <?=$form->field($model, "message[{$language_id}]")->label(false)->widget(\dosamigos\tinymce\TinyMce::className(), [
                    'options' => [
                            'rows' => 6,
                            'value' => $model->getMessage($language_id),
                    ],
                    'language' => 'ru',
                    'clientOptions' => [
                        'plugins' => [
                            //  "advlist",
                            // "paste"
                            'emoticons',
                            'link',
                            'code',
                        ],
                        'menubar' => false,
                        'resize' => false,
                        'statusbar' => false,
                        'paste_as_text' => true,
                        'toolbar' => "undo redo bold italic emoticons link  | code",
                        'default_link_target' => '_blank',
                        'link_context_toolbar' => true
                    ],
                ]);
                ?>
                <?= $form->field($model, "is_delete_image[{$language['language_id']}]")
                         ->label(false)
                         ->hiddenInput(['value' => '0', 'class' => 'is_delete_image']); ?>
            </div>
            <?php endforeach; ?>
            <div class="telegram_message_buttons_wrap">
                <div class="telegram_message_buttons" id="sortable-buttons">
                    <?php foreach ($model->telegramConstructorButtons as $i => $button): ?>
                    <?=$this->render('button', [
                        'messageId' => $button->callback_telegram_constructor_message_id,
                        'url' => $button->url,
                        'languages' => $languages,
                        'titles' => $button->getButonsText(),
                        'index' => $i + 1,
                    ]);?>
                    <?php endforeach; ?>
                </div>
<!--                <a href="#" class="add button_add" data-toggle="modal" data-modal-form="role_form" data-target="#modalFormAddButtonTgConstructor">Добавить кнопку</a>-->
            </div>
        </div>
        <div class="tg-preview_bg"></div>
    </div>


        <div class="form-group">
            <?= Html::submitButton('<i class="bi bi-check-circle"></i> Сохранить', ['class' => 'ds-btn ds-btn--success']) ?>
        </div>

        <?php ActiveForm::end(); ?>
    </div>
</div>

<div class="modal fade" id="modalFormAddButtonTgConstructor" tabindex="-1" role="dialog" aria-labelledby="modalForm">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <?php foreach ($languages as $language): ?>
                    <div class="form-group">
                        <label class="control-label language-picker large"><i class="<?=$language['language_id']?>"></i> <?=strtoupper($language['language'])?></label>
                        <input type="text" class="form-control telegramConstructorMessageButtonTitle" data-language="<?=$language['language_id']?>" value=""/>
                    </div>
                <?php endforeach; ?>
                <div class="form-group">
                    <label class="control-label">Перейти по внешней ссылке:</label>
                    <input type="text" class="form-control" id="telegramConstructorMessageButtonUrl" value=""/>
                </div>
                <div class="form-group">
                    <label class="control-label">Отправить ответное сообщение:</label>
                    <?= $form->field($model, 'message')->label(false)->widget(\kartik\select2\Select2::class, [
                        'data'    => \yii\helpers\ArrayHelper::merge(['0' => 'Выберите сообщение...'], TelegramConstructorMessage::getList()),
                        'options' => [
                            'id' =>  'telegramConstructorMessageButtonMessageId',
                            'prompt' => 'Выберите сообщение...',
                        ],
                        'showToggleAll' => false
                    ]); ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary addButton" data-dismiss="modal">Сохранить</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Отмена</button>
            </div>
        </div>
    </div>
</div>

<?php
$languagesStr = json_encode($languages);
$this->registerJs(<<<JS
    var languages = {$languagesStr};
JS
, \yii\web\View::POS_HEAD);
?>
