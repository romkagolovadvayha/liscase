<?php
/** @var int $dropId */

$drops = \common\models\box\DropStat::find()
    ->andWhere(['drop_id' => $dropId])
    ->all();
?>

<div class="mb-3">
    <?php foreach ($drops as $item): ?>
        <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-[hsl(0_0%_15%_/_1)] border border-[hsl(0_0%_20%_/_1)] mb-1.5 text-sm text-white">
            <span><?= htmlspecialchars($item->stat_key) ?> (<?= htmlspecialchars($item->value) ?>)</span>
            <a href="/drop-stat/delete?id=<?= (int)$item->id ?>" class="text-red-400 hover:text-red-300 no-underline">Удалить</a>
        </div>
    <?php endforeach; ?>
</div>
