<?php

use yii\helpers\Html;
use yii\helpers\Url;
use common\models\site\SiteSetting;

/** @var SiteSetting $item */

?>
<div class="setting_items_item_block flex flex-col gap-2">
    <div class="flex items-center justify-between gap-2">
        <label class="text-xs font-medium text-zinc-400"><?= Html::encode($item->name) ?></label>
        <a href="<?= Url::to(['/settings/update', 'id' => $item->id]) ?>" class="ds-btn ds-btn--icon ds-btn--ghost ds-btn--sm" title="<?= Yii::t('common', 'Редактировать') ?>"><i class="fas fa-pen"></i></a>
    </div>
    <span class="text-[10px] text-zinc-500 font-mono"><?= Html::encode($item->category) ?>_<?= Html::encode($item->code) ?></span>
    <div class="flex flex-wrap items-center gap-2">
        <input type="color" class="color_picker h-9 w-14 cursor-pointer rounded border border-[hsl(0_0%_15.3%_/_1)] bg-[hsl(0_0%_15%_/_1)] p-0.5" name="settings[<?= (int)$item->id ?>]" value="<?= Html::encode($item->value) ?>" />
        <input type="text" class="color_picker_text ds-input form-control w-24 text-sm" value="<?= Html::encode($item->value) ?>" />
    </div>
</div>