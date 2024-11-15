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
                <img src="<?=$url?>"/>
            </a>
        </div>
    <?php elseif (strpos($mimeType,'video') !== false): ?>
        <video class="blog_item_body_text_images_item_video" controls>
            <source src="<?=$url?>" type="<?=$mimeType?>">
            Your browser does not support the video tag.
        </video>
        <a href="<?=$url?>" target="_blank" class="blog_item_body_text_images_item_button"><i class="fa-solid fa-photo-film"></i> Скачать <?=$name?></a>
    <?php else: ?>
        <a href="<?=$url?>" target="_blank" class="blog_item_body_text_images_item_button"><i class="fa-regular fa-file"></i> Скачать <?=$name?></a>
    <?php endif; ?>
</div>
