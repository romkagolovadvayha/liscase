<?php

use yii\helpers\Html;
use yii\helpers\Url;
use common\models\site\SiteSetting;

/** @var SiteSetting $item */

?>
<div class="setting_items_item_block setting_items_item_block_text flex flex-col gap-2 col-span-1 lg:col-span-2">
    <div class="flex items-center justify-between gap-2">
        <label class="text-xs font-medium text-zinc-400"><?= Html::encode($item->name) ?></label>
        <a href="<?= Url::to(['/settings/update', 'id' => $item->id]) ?>" class="ds-btn ds-btn--icon ds-btn--ghost ds-btn--sm" title="<?= Yii::t('common', 'Редактировать') ?>"><i class="fas fa-pen"></i></a>
    </div>
    <span class="text-[10px] text-zinc-500 font-mono"><?= Html::encode($item->category) ?>_<?= Html::encode($item->code) ?></span>
    <?= Html::textarea('settings[' . $item->id . ']', $item->value, ['class' => 'ds-textarea form-control w-full min-h-[120px]', 'rows' => 5]) ?>
</div>