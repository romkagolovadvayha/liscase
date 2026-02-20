<?php

use common\models\box\Drop;
use yii\helpers\Html;

/** @var Drop $item */

$imageUrl = $item->image();
?>
<div class="drop-sort-card" title="<?= Html::encode($item->name) ?>">
    <?php if ($imageUrl): ?>
        <div class="drop-sort-card__img" style="background-image: url(<?= Html::encode($imageUrl) ?>)"></div>
    <?php else: ?>
        <div class="drop-sort-card__placeholder">—</div>
    <?php endif; ?>
    <input type="hidden" name="items[]" value="<?= (int) $item->id ?>">
</div>
