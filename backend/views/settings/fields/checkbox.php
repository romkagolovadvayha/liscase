<?php

use yii\helpers\Html;
use common\models\site\SiteSetting;

/** @var SiteSetting $item */

?>

<label class="setting_items_item_block">
    <span class="setting_items_item_block_name">
        <span><?= Html::encode($item->name) ?></span>
        <span class="setting_items_item_block_code"><?=$item->category?>_<?=$item->code?></span>
    </span>
    <span>
        <?=Html::hiddenInput('settings[' . $item->id . ']', 0)?>
        <?=Html::checkbox('settings[' . $item->id . ']', $item->getValue(), ['class' => 'show-statistics-block__switch none'])?>
        <span>
            <span class="icons icons_switch icons_switch_on"></span>
            <span class="icons icons_switch icons_switch_off"></span>
        </span>
    </span>
</label>
