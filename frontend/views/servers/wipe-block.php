<?php

use common\models\box\Drop;

/** @var yii\web\View $this */

/** @var Drop[] $drops */
$drops = Drop::find()
            ->andWhere(['market_status' => Drop::MARKET_STATUS_ACTIVE])
            ->andWhere('blocked_hour IS NOT NULL')
            ->orderBy(['blocked_hour' => SORT_ASC])
            ->all();

//            ->createCommand()
//            ->execute();
$results = [];
foreach ($drops as $drop) {
    if (empty($results[$drop->blocked_hour])) {
        $results[$drop->blocked_hour] = [];
    }
    $results[$drop->blocked_hour][] = $drop;
}

?>
<div class="wipe_block">
    <?php foreach ($results as $blockedHour => $items): ?>
    <div class="wipe_block_title"><?=$blockedHour?> <?= Yii::t('common', "часа") ?></div>
    <div class="wipe_block_list">
        <?php foreach ($items as $drop): ?>
            <div class="wipe_block_list_item">
                <div class="wipe_block_list_item_image">
                    <img src="<?= $drop->imageOrig->getImagePubUrl() ?>" alt="<?= Yii::t('database', $drop->name) ?>">
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>