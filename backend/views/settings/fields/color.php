<?php

use yii\helpers\Html;
use common\models\site\SiteSetting;

/** @var SiteSetting $item */

?>

<div class="setting_items_item_block">
    <span class="setting_items_item_block_name">
        <span><?= Html::encode($item->name) ?></span>
        <span class="setting_items_item_block_code"><?=$item->category?>_<?=$item->code?></span>
    </span>
    <span>
        <input type="text" class="color_picker_text form-control" style="width: 90px;" value="<?=$item->value?>" />
    </span>
    <span>
        <input type="color" class="color_picker form-control" name="settings[<?=$item->id?>]" value="<?=$item->value?>" />
    </span>
</div>