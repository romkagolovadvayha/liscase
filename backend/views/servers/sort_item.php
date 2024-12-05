<?php

use common\models\box\Drop;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

/** @var $item \common\models\servers\Servers */

?>
<div class="grid-item text-danger item_sort" title="<?=$item->name?>">
    <div><?=$item->name?></div>
    <input type="hidden" name="items[]" value="<?=$item->id?>">
</div>