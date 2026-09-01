<?php

/* @var $messageId int */
/* @var $url string */
/* @var $languages array */
/* @var $titles array */
/* @var $index int */

use yii\bootstrap5\Html;

?>
<div class="telegram_message_buttons_item_wrap">
    <div class="telegram_message_buttons_item">
        <a href="#" class="telegram_message_buttons_item_delete" title="Удалить"><span aria-hidden="true">&times;</span></a>
        <a href="#" class="telegram_message_buttons_item_update" title="Изменить" data-bs-toggle="modal" data-modal-form="role_form" data-bs-target="#modalFormAddButtonTgConstructor"><?=Html::icon('pencil')?></a>
        <?php foreach ($titles as $title): ?>
            <input type="hidden" class="button_title" name="TelegramConstructorMessageForm[buttons][<?=$index?>][title][<?=$title['language']?>]" data-language="<?=$title['language']?>" value="<?=$title['text']?>"/>
        <?php endforeach; ?>
        <div class="telegram_message_buttons_item_title"><?=$titles[0]['text']?></div>
        <?php if (!empty($messageId)): ?>
        <div class="telegram_message_buttons_item_action">
            <input type="hidden" class="button_messageId" name="TelegramConstructorMessageForm[buttons][<?=$index?>][message]" value="<?=$messageId?>"/>
            <a href="/telegram-constructor-message/view?id=<?=$messageId?>" target="_blank">Ответное сообщение</a>
        </div>
        <?php endif; ?>
        <?php if (!empty($url)): ?>
        <div class="telegram_message_buttons_item_action">
            <input type="hidden" class="button_url" name="TelegramConstructorMessageForm[buttons][<?=$index?>][url]" value="<?=$url?>"/>
            <a href="<?=$url?>" target="_blank">Переход по ссылке</a>
        </div>
        <?php endif; ?>
    </div>
</div>
