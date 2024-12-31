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
<div class="grid gap-y-24 px-24 mb-24">
    <div class="relative z-1 grid gap-y-32">
        <?php foreach ($results as $blockedHour => $items): ?>
            <article>
                <h5 class="mb-12"><?=$blockedHour?> <?= Yii::t('common', "часа") ?></h5>
                <div class="vape-block__list mb-10">
                    <?php foreach ($items as $drop): ?>
                        <div class="vape-block" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="<?= Yii::t('database', $drop->name) ?>">
                            <img class="vape-block__image" src="<?= $drop->imageOrig->getImagePubUrl() ?>" alt="<?= Yii::t('database', $drop->name) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>