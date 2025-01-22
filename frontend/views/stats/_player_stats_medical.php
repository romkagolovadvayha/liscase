<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $images */
/** @var array $names */
/** @var array $player */
/** @var string $steamId */
/** @var \common\models\user\User $user */

$keys = ['largemedkit', 'syringe', 'bandage'];

$items = [];
foreach ($keys as $key) {
    $items[] = Statistics::getFoodItem($images, $names, $player, $key);
}

?>
<!-- Медицина -->
<section class="page-stats__block-without-hover w-50p">
    <header class="flex items-center justify-space-between mb-24 transition-all">
        <h4 class="flex items-center gap-x-12">
            <?=Yii::t('common', 'Медицина')?><span
                    class="icons icons_24px icons_24px_info icons_hover"
                    data-bs-toggle="tooltip"
                    data-bs-placement="right"
                    data-bs-title="<?=Yii::t('common', 'Количество использованных медицинских инструментов.')?>"
            ></span>
        </h4>

        <label class="page-stats__show-statistics-block">
            <p class="p1 text-text-teritiary"><?=Yii::t('common', 'Показывать')?></p>
            <input checked type="checkbox" class="show-statistics-block__switch none" />
            <span>
                      <span class="icons icons_switch icons_switch_on"></span>
                      <span class="icons icons_switch icons_switch_off"></span>
                    </span>
        </label>
    </header>

    <div class="page-stats__categories">
        <?php foreach ($items as $item): ?>
            <div class="page-stats__category category">
                <h5 class="category__count-and-img">
                    <span><?= $item['desc'] ?></span>
                    <img src="<?= $item['image'] ?>" alt="<?= $item['name'] ?>" class="w-64 h-64 object-contain"/>
                </h5>
                <p class="category__title"><?= $item['name'] ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>
