<?php

use common\models\box\Drop;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use common\models\tasks\Task;
use yii\helpers\ArrayHelper;

/** @var $item Task */

?>
<style>
    .sortable.grid li {
        width: 100%;
        text-align: left;
        min-height: 0;
    }
</style>
<div class="item_sort">
    <?=$item->description?>
    <input type="hidden" name="items[]" value="<?=$item->id?>">
</div>