<?php

use common\models\servers\Servers;
use common\models\statistics\Kills;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $images */
/** @var array $names */
/** @var array $player */
/** @var string $steamId */
/** @var \common\models\user\User $user */

$kills = Kills::find()
              ->select(['weapon', 'COUNT(*) as count'])
              ->andWhere(['steam_id' => $steamId])
              ->andWhere(['server_tag' => $server->tag])
              ->andWhere(['wipe' => $server->currentWipe()])
              ->andWhere('weapon IS NOT NULL')
              ->asArray()
              ->groupBy('weapon')
              ->orderBy(['count' => SORT_DESC])
              ->all();

$weapons = [];
foreach ($kills as $item) {
    if (empty($item['weapon'])) {
        continue;
    }
    $weapons[] = [
        'weapon' => $item['weapon'],
        'image' => Statistics::getImage($images, $item['weapon']),
        'name'   => Statistics::getName($names, $item['weapon']),
        'count'  => $item['count'],
    ];
}

if (empty($weapons)) {
    return;
}
?>
<!-- Орудия убийства -->
<section class="page-stats__block-without-hover">
    <header class="flex items-center justify-space-between mb-24 transition-all">
        <h4 class="flex items-center gap-x-12">
            <?=Yii::t('common', 'Орудия убийства')?><span
                    class="icons icons_24px icons_24px_info icons_hover"
                    data-bs-toggle="tooltip"
                    data-bs-placement="right"
                    data-bs-title="<?=Yii::t('common', 'У каждого оружия указано количество убитых')?>"
            ></span>
        </h4>
<!---->
<!--        <label class="page-stats__show-statistics-block">-->
<!--            <p class="p1 text-text-teritiary">--><?//=Yii::t('common', 'Показывать')?><!--</p>-->
<!--            <input checked type="checkbox" class="show-statistics-block__switch none" />-->
<!--            <span>-->
<!--                    <span class="icons icons_switch icons_switch_on"></span>-->
<!--                    <span class="icons icons_switch icons_switch_off"></span>-->
<!--                  </span>-->
<!--        </label>-->
    </header>

    <div class="page-stats__categories">
        <?php foreach ($weapons as $weapon): ?>
            <div class="page-stats__category category">
                <h5 class="category__count-and-img">
                    <span><?= $weapon['count'] ? $weapon['count'] : 0 ?></span>
                    <img src="<?= $weapon['image'] ?>" alt="<?= $weapon['weapon'] ?>" class="w-64 h-64 object-contain"/>
                </h5>
                <p class="category__title"><?= Yii::t('database', $weapon['name']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>