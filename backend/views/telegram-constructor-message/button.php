<?php

use yii\helpers\Html;

/** @var int|null $messageId */
/** @var string|null $url */
/** @var array $titles */
/** @var int $index */

$firstTitle = $titles[0]['text'] ?? '';
?>
<div class="telegram_message_buttons_item_wrap">
    <div class="telegram_message_buttons_item">
        <?php foreach ($titles as $title): ?>
            <?= Html::hiddenInput(
                "TelegramConstructorMessageForm[buttons][{$index}][title][{$title['language']}]",
                $title['text'],
                ['class' => 'button_title', 'data-language' => $title['language']]
            ) ?>
        <?php endforeach; ?>
        <?php if ($messageId): ?>
            <?= Html::hiddenInput("TelegramConstructorMessageForm[buttons][{$index}][message]", (int)$messageId, ['class' => 'button_messageId']) ?>
        <?php endif; ?>
        <?php if ($url): ?>
            <?= Html::hiddenInput("TelegramConstructorMessageForm[buttons][{$index}][url]", $url, ['class' => 'button_url']) ?>
        <?php endif; ?>
        <span class="telegram_message_buttons_item_drag" title="Перетащите для изменения порядка"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></span>
        <span class="telegram_message_buttons_item_title"><?= Html::encode($firstTitle ?: 'Кнопка без названия') ?></span>
        <span class="telegram_message_buttons_item_type"><?= $messageId ? 'Ответ' : 'Ссылка' ?></span>
        <span class="telegram_message_buttons_item_controls">
            <button type="button" class="telegram_message_buttons_item_update" title="Изменить" aria-label="Изменить кнопку" data-bs-toggle="modal" data-bs-target="#modalFormAddButtonTgConstructor"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
            <button type="button" class="telegram_message_buttons_item_delete" title="Удалить" aria-label="Удалить кнопку"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
        </span>
    </div>
</div>
