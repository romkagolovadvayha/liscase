<?php
/** @var int $blogId */

use common\models\blog\BlogImage;

$images = BlogImage::find()
                   ->andWhere(['blog_id' => $blogId])
                   ->all();
?>

<div class="form-group">
    <?php foreach ($images as $item): ?>
        <div class="blog_item_body_text_images_item">
            <?=$this->render('_file-list-images', [
                'url' => $item->getPublicUrl(),
                'name' => $item->link
            ])?>
            <a href="/blog-image/delete?id=<?=$item->id?>" style="color: red;">Удалить</a>
        </div>
    <?php endforeach; ?>
</div>
