<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $images */
/** @var array $names */
/** @var array $player */
/** @var string $steamId */
/** @var \common\models\user\User $user */

$list = [
    [
        'name'  => Yii::t('common', 'Анчоус'),
        'key' => 'f_fish.anchovy',
        'score' => 10,
    ],
    [
        'name'  => Yii::t('common', 'Сом'),
        'key' => 'f_fish.catfish',
        'score' => 32,
    ],
    [
        'name'  => Yii::t('common', 'Cельдь'),
        'key' => 'f_fish.herring',
        'score' => 10,
    ],
    [
        'name'  => Yii::t('common', 'Большеголов'),
        'key' => 'f_fish.orangeroughy',
        'score' => 37,
    ],
    [
        'name'  => Yii::t('common', 'Лосось'),
        'key' => 'f_fish.salmon',
        'score' => 22,
    ],
    [
        'name'  => Yii::t('common', 'Сардина'),
        'key' => 'f_fish.sardine',
        'score' => 10,
    ],
    [
        'name'  => Yii::t('common', 'Акула'),
        'key' => 'f_fish.smallshark',
        'score' => 45,
    ],
    [
        'name'  => Yii::t('common', 'Форель'),
        'key' => 'f_fish.troutsmall',
        'score' => 15,
    ],
    [
        'name'  => Yii::t('common', 'Окунь'),
        'key' => 'f_fish.yellowperch',
        'score' => 25,
    ],
];

$items = [];
foreach ($list as $item) {
    $items[] = Statistics::getFishItem($images, $player, $item['key'], $item['name'], $item['score']);
}

usort(
    $items,
    function ($a, $b) {
        return ($b['score'] - $a['score']);
    }
);

?>
<!-- Рыбалка -->
<section class="page-stats__block-without-hover">
    <header class="flex items-center justify-space-between mb-24 transition-all">
        <h4 class="flex items-center gap-x-12">
            <?=Yii::t('common', 'Рыбаловство')?><span
                    class="icons icons_24px icons_24px_info icons_hover"
                    data-bs-toggle="tooltip"
                    data-bs-placement="right"
                    data-bs-title="<?=Yii::t('common', 'Количество рыбы, которую игрок поймал за вайп.')?>"
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
                    <span><?= $item['desc'] ?><span class="category__x"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-placement="bottom"
                                                    data-bs-title="<?=Yii::t('common', 'Множитель для рейтинга игроков') . " x" . $item['score']?>">x<?= $item['score'] ?></span></span>
                    <img src="<?= $item['image'] ?>" alt="" class="w-64 h-64 object-contain" />
                </h5>
                <p class="category__title"><?= Yii::t('database', $item['name']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>
