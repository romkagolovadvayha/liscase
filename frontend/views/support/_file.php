<?php


/** @var yii\web\View $this */
/** @var \common\models\support\SupportFile $model */

?>
<div class="support_messages_item_message_files_item">
    <?php if (in_array($model->mimetype, ['image/png', 'image/jpeg', 'image/gif'])): ?>
        <a href="/uploads/chat/<?=$model->file?>"
           class="support_messages_item_message_files_item_preview"
           title="<?=$model->filename?>"
           target="_blank">
            <img src="/uploads/chat/<?=$model->file?>"/>
        </a>
    <?php elseif (strpos( $model->mimetype,'video') !== false): ?>
        <div class="support_messages_item_message_files_item_name">Видео:</div>
        <a href="/uploads/chat/<?=$model->file?>" target="_blank"><?=$model->filename?></a>
    <?php else: ?>
        <div class="support_messages_item_message_files_item_name">Файл:</div>
        <a href="/uploads/chat/<?=$model->file?>" target="_blank"><?=$model->filename?></a>
    <?php endif; ?>
</div>
