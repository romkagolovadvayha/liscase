<?php


/** @var yii\web\View $this */
/** @var \common\models\support\SupportFile $model */

?>
<div class="support_messages_item_message_files_item">
    <?php if (in_array($model->mimetype, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'])): ?>
        <a href="<?=Yii::$app->params['s3Url']?>/support/<?=$model->file?>"
           class="support_messages_item_message_files_item_preview"
           title="<?=$model->filename?>"
           target="_blank">
            <img src="<?=Yii::$app->params['s3Url']?>/support/<?=$model->file?>"/>
        </a>
    <?php elseif (strpos( $model->mimetype,'video') !== false): ?>
        <video class="support_messages_item_message_files_item_video" controls>
            <source src="<?=Yii::$app->params['s3Url']?>/support/<?=$model->file?>" type="<?=$model->mimetype?>">
            Your browser does not support the video tag.
        </video>
        <a href="<?=Yii::$app->params['s3Url']?>/support/<?=$model->file?>" target="_blank" class="support_messages_item_message_files_item_button"><i class="fa-solid fa-photo-film"></i> Скачать <?=$model->filename?></a>
    <?php else: ?>
        <a href="<?=Yii::$app->params['s3Url']?>/support/<?=$model->file?>" target="_blank" class="support_messages_item_message_files_item_button"><i class="fa-regular fa-file"></i> Скачать <?=$model->filename?></a>
    <?php endif; ?>
</div>
