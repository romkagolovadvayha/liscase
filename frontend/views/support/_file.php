<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var \common\models\support\SupportFile $model */

?>
<?php
$fileUrl = $model->getPublicUrl();
?>
<div class="support_messages_item_message_files_item">
    <?php if (in_array($model->mimetype, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'])): ?>
        <a href="<?=$fileUrl?>"
           class="support_messages_item_message_files_item_preview support-image-viewer"
           title="<?=$model->filename?>"
           data-image-url="<?=$fileUrl?>"
           data-image-title="<?=Html::encode($model->filename)?>">
            <img src="<?=$fileUrl?>"/>
        </a>
    <?php elseif (strpos( $model->mimetype,'video') !== false): ?>
        <video class="support_messages_item_message_files_item_video" controls>
            <source src="<?=$fileUrl?>" type="<?=$model->mimetype?>">
            Your browser does not support the video tag.
        </video>
        <a href="<?=$fileUrl?>" target="_blank" class="support_messages_item_message_files_item_button"><i class="fa-solid fa-photo-film"></i> Скачать <?=$model->filename?></a>
    <?php else: ?>
        <a href="<?=$fileUrl?>" target="_blank" class="support_messages_item_message_files_item_button"><i class="fa-regular fa-file"></i> Скачать <?=$model->filename?></a>
    <?php endif; ?>
</div>
