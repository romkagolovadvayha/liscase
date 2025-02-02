<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $images */
/** @var array $names */
/** @var array $player */
/** @var string $steamId */
/** @var \common\models\user\User $user */

$wolf = Statistics::getParam($player, 'wolf');
if (empty($wolf)) {
    $wolf = Statistics::getParam($player, 'wolf2') + Statistics::getParam($player, 'wolf');
}
$hunters = [
    [
        'name'  => Yii::t('common', 'Кабаны'),
        'count' => Statistics::getParam($player, 'boar'),
        'image'  => '/images/hunters/Boar.png',
    ],
    [
        'name'  => Yii::t('common', 'Лошади'),
        'count' => Statistics::getParam($player, 'horse'),
        'image'  => '/images/hunters/Horse.png',
    ],
    [
        'name'  => Yii::t('common', 'Волки'),
        'count' => $wolf,
        'image'  => '/images/hunters/Wolf.png',
    ],
    [
        'name'  => Yii::t('common', 'Медведи'),
        'count' => Statistics::getParam($player, 'bear'),
        'image'  => '/images/hunters/bear.png',
    ],
    [
        'name'  => Yii::t('common', 'Белые медведи'),
        'count' => Statistics::getParam($player, 'polarbear'),
        'image'  => '/images/hunters/Polarbear.png',
    ],
    [
        'name'  => Yii::t('common', 'Олени'),
        'count' => Statistics::getParam($player, 'deer'),
        'image'  => '/images/hunters/Stag.png',
    ],
    [
        'name'  => Yii::t('common', 'Курицы'),
        'count' => Statistics::getParam($player, 'chicken'),
        'image'  => '/images/hunters/Chicken.png',
    ],
];

?>
<!-- Охота -->
<section class="page-stats__block-without-hover">
    <header class="flex items-center justify-space-between mb-24 transition-all">
        <h4 class="flex items-center gap-x-12">
            <?=Yii::t('common', 'Охота')?><span
                    class="icons icons_24px icons_24px_info icons_hover"
                    data-bs-toggle="tooltip"
                    data-bs-placement="right"
                    data-bs-title="<?=Yii::t('common', 'Количество убитых животных игроком.')?>"
            ></span>
        </h4>

<!--        <label class="page-stats__show-statistics-block">-->
<!--            <p class="p1 text-text-teritiary">--><?//=Yii::t('common', 'Показывать')?><!--</p>-->
<!--            <input checked type="checkbox" class="show-statistics-block__switch none" />-->
<!--            <span>-->
<!--                      <span class="icons icons_switch icons_switch_on"></span>-->
<!--                      <span class="icons icons_switch icons_switch_off"></span>-->
<!--                    </span>-->
<!--        </label>-->
    </header>

    <div class="page-stats__categories">
        <?php foreach ($hunters as $item): ?>
            <div class="page-stats__category category">
                <h5 class="category__count-and-img">
                    <span><?= $item['count'] ?></span>
                    <img src="<?= $item['image'] ?>" alt="<?= $item['name'] ?>" class="w-64 h-64 object-contain"/>
                </h5>
                <p class="category__title"><?= $item['name'] ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>