<?php


/** @var yii\web\View $this */
/** @var string $url */
/** @var string $name */

$urlData = explode('.', $url);
$ext = $urlData[count($urlData) - 1];
$mimeType = \common\models\blog\BlogImage::getMimetypeFromExtension($ext);
?>
<div class="blog_item_body_text_images_item">
    <?php if (in_array($mimeType, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'])): ?>
        <div class="blog_item_body_text_images_item_preview_wrap">
            <a href="<?=$url?>"
               class="blog_item_body_text_images_item_preview"
               title="<?=$name?>">
                <img src="<?= \yii\helpers\Html::encode($url) ?>" width="64" height="64" alt="<?= \yii\helpers\Html::encode($name) ?>" />
            </a>
        </div>
    <?php elseif (strpos($mimeType,'video') !== false): ?>
        <a href="<?=$url?>" target="_blank" class="blog_item_body_text_images_item_button"><?=$name?></a>
    <?php else: ?>
        <a href="<?=$url?>" target="_blank" class="blog_item_body_text_images_item_button"><?=$name?></a>
    <?php endif; ?>
</div>
