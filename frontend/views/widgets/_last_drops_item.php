<?php
use yii\web\View;

/** @var View $this */
/** @var array $item */

?>
<div class="last_drops_item level<?= $item['type'] ?>">
    <div class="last_drops_item_content">
        <div class="last_drops_item_image">
            <img src="<?= $item['image'] ?>" alt="<?= $item['name'] ?>">
        </div>
        <?php if (!empty($item['bgImage'])): ?>
            <div class="last_drops_item_box_image">
                <img src="<?= $item['bgImage'] ?>" alt="<?= $item['bgName'] ?>">
            </div>
        <?php endif; ?>
        <div class="last_drops_item_title"><?=$item['name']?></div>
    </div>
    <div class="last_drops_item_content_back">
        <div class="last_drops_item_user">
            <?php if (!empty($item['userAvatar'])): ?>
                <div class="last_drops_item_user_avatar">
                    <img src="<?=$item['userAvatar']?>" alt="<?=$item['userName']?>">
                </div>
            <?php endif; ?>
            <div class="last_drops_item_user_name"><?=$item['userName']?></div>
        </div>
        <div class="last_drops_item_user_count"><?=$item['count']?></div>
    </div>
</div>