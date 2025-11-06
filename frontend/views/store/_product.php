<?php

use common\models\box\DropBlocked;

/** @var \common\models\box\Drop $drop */
/** @var int $serverId */
/** @var \common\models\user\UserDrop $userDrop */

$blockedAt = DropBlocked::getBlocked($drop->id, $serverId);
$blocked = !empty($blockedAt);
// Стили перенесены в launcher.scss
?>
<div class="store_launcher_cards_item_wrap" data-category-id="<?=$drop->category_id?>" data-title="<?=Yii::t('database', $drop->name)?>">
    <div class="store_launcher_cards_item category-card" data-id="<?=$userDrop->id?>">
        <div class="store_launcher_cards_item_image category-card__image">
            <img src="<?= $drop->image100() ?>" alt="<?=Yii::t('database', $drop->name)?>" loading="lazy">
        </div>
        <?php if ($userDrop->count > 1): ?>
            <div class="store_launcher_cards_item_count category-card__boost">
                x<?= $userDrop->count ?>
            </div>
        <?php endif; ?>
        <?php if (!$blocked): ?>
            <div class="store_launcher_cards_item_button">
                <?=Yii::t('common', 'Получить')?>
            </div>
        <?php endif; ?>
        <?php if ($blocked): ?>
            <div class="store_launcher_cards_item_blocked_wrap">
                <div class="store_launcher_cards_item_blocked_image">
                    <img src="<?= $drop->image100() ?>" alt="<?=Yii::t('database', $drop->name)?>">
                </div>
                <div class="store_launcher_cards_item_blocked_title"><?=Yii::t('database', $drop->name)?></div>
                <div class="store_launcher_cards_item_blocked_subtitle">🔒 <?=Yii::t('common', 'Вайп блок')?></div>
                <div class="store_launcher_cards_item_blocked_timer blocked_products_timer" data-time="<?=strtotime($blockedAt)?>"><?=$blockedAt?></div>
            </div>
        <?php endif; ?>
    </div>
</div>