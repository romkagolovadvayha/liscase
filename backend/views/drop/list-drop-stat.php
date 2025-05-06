<?php
/** @var int $dropId */

use common\models\blog\BlogImage;

/** @var \common\models\box\DropStat[] $drops */
$drops = \common\models\box\DropStat::find()
                   ->andWhere(['drop_id' => $dropId])
                   ->all();
?>

<div class="form-group">
    <?php foreach ($drops as $item): ?>
        <div class="blog_item_body_text_images_item">
            <?=$item->stat_key?> (<?=$item->value?>)
            <a href="/drop-stat/delete?id=<?=$item->id?>" style="color: red;">Удалить</a>
        </div>
    <?php endforeach; ?>
</div>
