<?php

use yii\helpers\Html;
use yii\helpers\Url;
use common\models\site\SiteSetting;

/** @var SiteSetting $item */

$checked = $item->getValue();
?>
<div class="setting_items_item_block flex flex-col gap-2">
    <div class="flex items-center justify-between gap-2">
        <label class="text-xs font-medium text-zinc-400"><?= Html::encode($item->name) ?></label>
        <a href="<?= Url::to(['/settings/update', 'id' => $item->id]) ?>" class="ds-btn ds-btn--icon ds-btn--ghost ds-btn--sm" title="<?= Yii::t('common', 'Редактировать') ?>"><i class="fas fa-pen"></i></a>
    </div>
    <span class="text-[10px] text-zinc-500 font-mono"><?= Html::encode($item->category) ?>_<?= Html::encode($item->code) ?></span>
    <label class="settings-checkbox-switch relative inline-flex items-center cursor-pointer w-fit">
        <?= Html::hiddenInput('settings[' . $item->id . ']', '0') ?>
        <?= Html::checkbox('settings[' . $item->id . ']', $checked, ['class' => 'settings-checkbox-input sr-only', 'value' => '1']) ?>
        <span class="settings-checkbox-slider"></span>
    </label>
</div>
