<?php

use yii\widgets\Pjax;
use frontend\widgets\Alert;

/** @var \common\models\map\MapList[] $maps */
/** @var array $cardsHtml */

$pjaxId = 'maps-v2-cards-pjax';

?>

<?php Pjax::begin([
    'id' => $pjaxId,
    'enablePushState' => false,
    'timeout' => 5000
]); ?>

<?= Alert::widget() ?>

<div class="mapsV2__cards" data-role="map-list">
    <?php foreach ($maps as $map): ?>
        <?= $cardsHtml[$map->id] ?? '' ?>
    <?php endforeach; ?>
</div>

<?php Pjax::end(); ?>

