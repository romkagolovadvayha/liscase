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
    <?php if (!empty($item->value)): ?>
        <div class="setting_items_item_block_image rounded border border-[hsl(0_0%_15.3%_/_1)] overflow-hidden bg-[hsl(0_0%_15%_/_1)] inline-block max-w-[200px]">
            <video class="block max-h-16 w-auto" playsinline preload="auto" loop muted>
                <source type="video/webm" src="<?= Html::encode($item->getValue()) ?>">
            </video>
        </div>
    <?php endif; ?>
    <?= Html::fileInput('settings[' . $item->id . ']', null, ['class' => 'ds-input form-control w-full text-sm file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:bg-[hsl(0_0%_20%_/_1)] file:text-zinc-200', 'accept' => '.webm']) ?>
</div>