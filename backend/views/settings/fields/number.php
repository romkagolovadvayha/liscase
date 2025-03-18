<?php

use yii\helpers\Html;
use common\models\site\SiteSetting;

/** @var SiteSetting $item */

?>

<div class="setting_items_item_block_text">
    <span class="setting_items_item_block_name">
        <span><?= Html::encode($item->name) ?> <a href="/settings/update?id=<?=$item->id?>"><i class="fas fa-pen"></i></a></span>
        <span class="setting_items_item_block_code"><?=$item->category?>_<?=$item->code?></span>
    </span>
    <span>
        <?=Html::textInput('settings[' . $item->id . ']', $item->value, ['class' => 'form-control'])?>
    </span>
</div>