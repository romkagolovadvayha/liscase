<?php
use common\models\box\Drop;

/** @var Drop $model */
?>
<div id="drop-items-list" class="flex flex-col gap-2 mt-2">
    <?php foreach ($model->subDrops as $subDrop): ?>
        <div class="flex gap-3 items-center justify-between py-2 px-3 rounded-lg bg-[hsl(0_0%_15%_/_1)] border border-[hsl(0_0%_20%_/_1)]">
            <div class="flex gap-3 items-center min-w-0">
                <img src="<?= $subDrop->drop->image() ?>" width="32" height="32" class="rounded flex-shrink-0" alt="" />
                <span class="text-sm text-white truncate"><?= htmlspecialchars($subDrop->drop->name) ?> (x<?= (int)$subDrop->count ?>)</span>
            </div>
            <a href="/drop-drop/delete?id=<?= (int)$subDrop->id ?>" class="delete-drop-item ds-btn ds-btn--sm text-red-400 hover:text-red-300 no-underline flex-shrink-0" data-id="<?= (int)$subDrop->id ?>" data-pjax="0">Убрать</a>
        </div>
    <?php endforeach; ?>
</div>
