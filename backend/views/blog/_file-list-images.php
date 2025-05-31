<?php


/** @var yii\web\View $this */
/** @var string $url */
/** @var string $name */

$urlData = explode('.', $url);
$ext = $urlData[count($urlData) - 1];
$mimeType = \common\models\blog\BlogImage::getMimetypeFromExtension($ext);
?>
    <?php if (in_array($mimeType, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'])): ?>
    <a href="<?=$url?>"
       class="blog_item_body_text_images_item_preview"
       title="<?=$name?>">
        <div class="blog_item_body_text_images_item_preview_wrap" style="width: 100px; height: 100px;background: url(<?=$url?>); background-size: cover; background-position: center center;">
        </div>
    </a>
    <?php elseif (strpos($mimeType,'video') !== false): ?>
        <a href="<?=$url?>" target="_blank" class="blog_item_body_text_images_item_button" style="width: 100px; height: 100px;"><?=$name?></a>
    <?php else: ?>
        <a href="<?=$url?>" target="_blank" class="blog_item_body_text_images_item_button" style="width: 100px; height: 100px;"><?=$name?></a>
    <?php endif; ?>
