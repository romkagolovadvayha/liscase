<?php
    /** @var \common\models\box\Drop $drop */
?>
<?php $blocked = !empty($drop->blocked_at) && strtotime($drop->blocked_at) > time(); ?>
<div data-href="/market/form-modal?id=<?=$drop->id?>" data-category-id="<?=$drop->category_id?>" class="products_item show-modal-link<?=!$blocked ? ' active' : ''?>" data-title="<?=Yii::t('database', $drop->name)?>" data-size="modal-sm" data-toggl="modal" data-target="modal-dialog">
    <div class="products_item_body">
        <div class="products_item_image">
            <img src="<?= $drop->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('database', $drop->name)?>" width="100px">
        </div>
        <div class="products_item_price">
            <?php if ($drop->discount > 0 && ceil($drop->price) != $drop->getRealPrice()): ?>
                <span class="products_item_price_old"><?=ceil($drop->price)?></span>
            <?php endif; ?>
            <span class="products_item_price_current"><?=$drop->getRealPrice()?></span>
            <span class="products_item_price_currency">RUB</span>
        </div>
        <div class="products_item_count">x<?=$drop->count?></div>
    </div>
    <div class="products_item_body_hover">
        <div class="products_item_title">
            <span><?=Yii::t('database', $drop->name)?></span>
        </div>
        <div class="products_item_title">
            <span class="products_item_price_current"><?=$drop->getRealPrice()?></span>
            <span class="products_item_price_currency">RUB</span>
        </div>
        <div class="products_item_title">x<?=$drop->count?></div>
    </div>
    <?php if ($blocked): ?>
        <div class="products_item_blocked_wrap">
            <div class="products_item_blocked_title">Вайп блок</div>
            <div class="products_item_blocked_timer blocked_products_timer" data-time="<?=strtotime($drop->blocked_at)?>"><?=$drop->blocked_at?></div>
        </div>
    <?php endif; ?>
</div>
