<?php

use backend\models\TelegramConstructorMessage;

/** @var string $messageId */

/** @var TelegramConstructorMessage $model */
$model = TelegramConstructorMessage::findOne($messageId);
?>

<div class="constructor_message_preview">
    <div class="tg-preview_wrap">
        <div class="tg-preview">
            <div class="tg-preview_message active">
                <?php if (!empty($model->getImageLink())): ?>
                    <img class="tg-preview-image" src="<?=$model->getPubUrl()?>" />
                <?php endif; ?>
                <div class="tg-preview_message_body">
                    <?=$model->getMessage()?>
                </div>
            </div>
            <div class="telegram_message_buttons_wrap">
                <div class="telegram_message_buttons" id="sortable-buttons">
                    <?php foreach ($model->telegramConstructorButtons as $i => $button): ?>
                        <div class="telegram_message_buttons_item_wrap">
                            <div class="telegram_message_preview_buttons_item">
                                <div class="telegram_message_buttons_item_title"><?=$button->getText()?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="tg-preview_bg"></div>
    </div>
</div>
