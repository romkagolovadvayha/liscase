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

$items = [
    ['key' => 'c4thrown', 'score' => 1],
    ['key' => 'satchelsthrown', 'score' => 0.2],
    ['key' => 'rocket_basic', 'score' => 0.5, 'combined' => ['rocket_basic_rpg']],
    ['key' => 'rocket_hv', 'score' => 0.1],
    ['key' => 'rocket_fire', 'score' => 0.1],
    ['key' => 'ammo_explosive', 'score' => 0.01],
    ['key' => 'grenade.f1.deployed', 'score' => 0.02],
    ['key' => 'grenade.molotov.deployed', 'score' => 0.05],
    ['key' => 'grenade.beancan.deployed', 'score' => 0.05],
    ['key' => 'grenade.flashbang.deployed', 'score' => 0],
    ['key' => 'grenade.supplysignal.deployed', 'score' => 0],
    ['key' => 'grenade.smoke.deployed', 'score' => 0],
    ['key' => 'grenade.bee.deployed', 'score' => 0],
    ['key' => '40mm_grenade_he', 'score' => 0],
    ['key' => '40mm_grenade_smoke', 'score' => 0],
    ['key' => 'rocket_heatseeker', 'score' => 0],
    ['key' => 'flare.deployed', 'score' => 0],
];

//$keys = [];
//foreach ($items as $item) {
//    $keys[] = str_replace('.deployed', '', $item['key']);
//}

$reider = [];
foreach ($items as $item) {
    $itemData = Statistics::getRaiderItem($names, $images, $player, $item['key'], $item['score']);
    
    // Если есть combined ключи, добавляем их значения к основному
    if (!empty($item['combined']) && is_array($item['combined'])) {
        $combinedCount = $itemData['count'];
        foreach ($item['combined'] as $combinedKey) {
            $combinedCount += Statistics::getParam($player, $combinedKey);
        }
        $itemData['count'] = $combinedCount;
        $itemData['desc'] = $combinedCount;
    }
    
    $reider[] = $itemData;
}

?>
<!-- Взрывные устройства -->
<section class="page-stats__block-without-hover">
    <header class="flex items-center justify-space-between mb-24 transition-all">
        <h3 class="flex items-center gap-x-12">
            <?=Yii::t('common', 'Взрывные устройства')?><span
                    class="icons icons_24px icons_24px_info icons_hover"
                    data-bs-toggle="tooltip"
                    data-bs-placement="right"
                    data-bs-title="<?=Yii::t('common', 'Количество использованных взрывчатых предметов игроком.')?>"
            ></span>
        </h3>

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
        <?php foreach ($reider as $item): ?>
            <div class="page-stats__category category">
                <h5 class="category__count-and-img">
                    <span>
                        <?= $item['desc'] ?><?php if (!empty($item['score'])): ?><span class="category__x"
                                  data-bs-toggle="tooltip"
                                  data-bs-placement="bottom"
                                  data-bs-title="<?=Yii::t('common', 'Множитель для рейтинга игроков') . " x" . $item['score']?>">x<?= $item['score'] ?></span><?php endif; ?>
                    </span>
                    <img src="<?= $item['image'] ?>" alt="" class="w-64 h-64 object-contain" />
                </h5>
                <p class="category__title"><?= Yii::t('database', $item['name']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>