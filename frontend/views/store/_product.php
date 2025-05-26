<?php

use common\models\box\DropBlocked;

/** @var \common\models\box\Drop $drop */
/** @var int $serverId */
/** @var \common\models\user\UserDrop $userDrop */

?>

<?php $blockedAt = DropBlocked::getBlocked($drop->id, $serverId); ?>
<?php $blocked = !empty($blockedAt); ?>
<div class="store_launcher_cards_item_wrap" data-category-id="<?=$userDrop->drop[0]->category_id?>">
    <div class="store_launcher_cards_item" data-id="<?=$userDrop->id?>">
        <div class="store_launcher_cards_item_image">
            <img src="<?= $drop->image100() ?>" alt="<?=Yii::t('database', $drop->name)?>">
        </div>
        <!--                        <div class="store_launcher_cards_item_title">--><?php //echo Yii::t('database', $drop->name)?><!--</div>-->
        <?php if ($userDrop->count > 1): ?>
            <div class="store_launcher_cards_item_count">
                x<?= $userDrop->count ?>
            </div>
        <?php endif; ?>
        <div class="store_launcher_cards_item_button<?=$blocked ? ' blocked' : ''?>">
            <?php if ($blocked): ?>
                <?=Yii::t('common', 'Недоступно')?>
            <?php else: ?>
                <?=Yii::t('common', 'Получить')?>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($blocked): ?>
        <div class="store_launcher_cards_item_blocked_wrap">
            <div class="store_launcher_cards_item_blocked_title"><?=Yii::t('common', 'Вайп блок')?></div>
            <div class="store_launcher_cards_item_blocked_timer blocked_products_timer" data-time="<?=strtotime($blockedAt)?>"><?=$blockedAt?></div>
        </div>
    <?php endif; ?>
</div>