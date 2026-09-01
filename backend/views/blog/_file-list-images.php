<?php


/** @var yii\web\View $this */
/** @var string $url */
/** @var string $name */

$urlData = explode('.', $url);
$ext = $urlData[count($urlData) - 1];
$mimeType = \common\models\blog\BlogImage::getMimetypeFromExtension($ext);
?>
    <?php if (in_array($mimeType, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'])): ?>
    <a href="<?= \yii\helpers\Html::encode($url) ?>"
       class="blog_item_body_text_images_item_preview"
       title="<?= \yii\helpers\Html::encode($name) ?>">
        <img class="blog-media-library__preview" src="<?= \yii\helpers\Html::encode($url) ?>" alt="<?= \yii\helpers\Html::encode($name) ?>" width="100" height="100" />
    </a>
    <?php elseif (strpos($mimeType,'video') !== false): ?>
        <a href="<?= \yii\helpers\Html::encode($url) ?>" target="_blank" rel="noopener" class="blog_item_body_text_images_item_button blog-media-library__file"><?= \yii\helpers\Html::encode($name) ?></a>
    <?php else: ?>
        <a href="<?= \yii\helpers\Html::encode($url) ?>" target="_blank" rel="noopener" class="blog_item_body_text_images_item_button blog-media-library__file"><?= \yii\helpers\Html::encode($name) ?></a>
    <?php endif; ?>
