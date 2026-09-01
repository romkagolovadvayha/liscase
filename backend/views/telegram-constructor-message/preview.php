<?php

use backend\models\TelegramConstructorMessage;
use yii\helpers\Html;

/** @var TelegramConstructorMessage $model */
$message = trim((string)$model->getMessage());
?>
<div class="constructor_message_preview">
    <div class="mailing-message-bubble">
        <?php if ($model->getImageLink()): ?>
            <?= Html::img($model->getPubUrl(), ['class' => 'mailing-message-bubble__image', 'alt' => 'Изображение сообщения']) ?>
        <?php endif; ?>
        <div class="mailing-message-bubble__body">
            <?= $message !== '' ? $message : '<span class="mailing-message-bubble__empty">В шаблоне нет текста.</span>' ?>
        </div>
        <?php if ($model->telegramConstructorButtons): ?>
            <div class="mailing-message-bubble__buttons">
                <?php foreach ($model->telegramConstructorButtons as $button): ?>
                    <span><?= Html::encode($button->getText() ?: 'Кнопка без названия') ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
