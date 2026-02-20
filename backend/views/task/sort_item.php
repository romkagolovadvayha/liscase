<?php

use common\models\tasks\Task;
use yii\helpers\Html;

/** @var Task $item */

?>
<div class="admin-sort-card" title="<?= Html::encode($item->description) ?>">
    <div class="admin-sort-card__body"><?= Html::encode($item->description) ?></div>
    <input type="hidden" name="items[]" value="<?= (int) $item->id ?>">
</div>
