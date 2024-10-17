<?php

use common\models\box\Drop;
use kartik\grid\GridView;
use yii\bootstrap5\Html;
use yii\helpers\ArrayHelper;

/** @var $item Drop */

?>
<div class="grid-item text-danger item_sort" style="background-image: url(<?=$item->imageOrig->getImagePubUrl()?>)" title="<?=$item->name?>">
    <input type="hidden" name="items[]" value="<?=$item->id?>">
</div>