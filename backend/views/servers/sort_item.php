<?php

use common\models\servers\Servers;
use yii\helpers\Html;

/** @var Servers $item */

?>
<div class="admin-sort-card" title="<?= Html::encode($item->name) ?>">
    <div class="admin-sort-card__body"><?= Html::encode($item->name) ?></div>
    <input type="hidden" name="items[]" value="<?= (int) $item->id ?>">
</div>
