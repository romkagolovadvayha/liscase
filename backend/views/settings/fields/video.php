<?php

use yii\helpers\Html;
use common\models\site\SiteSetting;

/** @var SiteSetting $item */

?>

<div class="setting_items_item_block">
    <span class="setting_items_item_block_name">
        <span><?= Html::encode($item->name) ?> <a href="/settings/update?id=<?=$item->id?>"><i class="fas fa-pen"></i></a></span>
        <span class="setting_items_item_block_code"><?=$item->category?>_<?=$item->code?></span>
    </span>
    <?php if (!empty($item->value)): ?>
        <span class="setting_items_item_block_image">
            <video class="case-video second" playsinline="" preload="auto" loop="" autoplay="" style="height: 30px;">
                <source type="video/webm" src="<?=$item->getValue()?>">
            </video>
        </span>
    <?php endif; ?>
    <span>
        <?=Html::fileInput('settings[' . $item->id . ']', null, ['class' => 'form-control', 'accept' => '.webm'])?>
    </span>
</div>