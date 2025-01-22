<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $images */
/** @var array $names */
/** @var array $player */
/** @var string $steamId */
/** @var \common\models\user\User $user */

$items = [
    ['key' => 'gathered_cloth', 'name' => Yii::t('common', 'Ткань'), 'score' => 0.05],
    ['key' => 'gathered_corn', 'name' => Yii::t('common', 'Кукуруза'), 'score' => 0.3],
    ['key' => 'gathered_potato', 'name' => Yii::t('common', 'Картофель'), 'score' => 0.4],
    ['key' => 'gathered_pumpkin', 'name' => Yii::t('common', 'Тыква'), 'score' => 0.5],
    ['key' => 'gathered_blue.berry', 'name' => Yii::t('common', 'Синие ягоды'), 'score' => 0.5],
    ['key' => 'gathered_yellow.berry', 'name' => Yii::t('common', 'Желтые ягоды'), 'score' => 0.5],
    ['key' => 'gathered_red.berry', 'name' => Yii::t('common', 'Красные ягоды'), 'score' => 0.5],
    ['key' => 'gathered_white.berry', 'name' => Yii::t('common', 'Белые ягоды'), 'score' => 0.5],
    ['key' => 'gathered_green.berry', 'name' => Yii::t('common', 'Зеленые ягоды'), 'score' => 0.5],
    ['key' => 'gathered_black.berry', 'name' => Yii::t('common', 'Черные ягоды'), 'score' => 1],
];

$fermers = [];
foreach ($items as $item) {
    $fermers[] = Statistics::getFermItem($images, $player, $item['key'], $item['name'], $item['score']);
}

?> <!-- Фермерство -->
<section class="page-stats__block-without-hover w-50p">
    <header class="flex items-center justify-space-between mb-24 transition-all">
        <h4 class="flex items-center gap-x-12">
            <?=Yii::t('common', 'Фермерство')?><span
                    class="icons icons_24px icons_24px_info icons_hover"
                    data-bs-toggle="tooltip"
                    data-bs-placement="right"
                    data-bs-title="<?=Yii::t('common', 'Количество выращенных растений игроком.')?>"
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
        <?php foreach ($fermers as $item): ?>
            <div class="page-stats__category category">
                <h5 class="category__count-and-img">
                    <span><?= $item['desc'] ?><span class="category__x">x<?= $item['score'] ?></span></span>
                    <img src="<?= $item['image'] ?>" alt="<?= $item['name'] ?>" class="w-64 h-64 object-contain"/>
                </h5>
                <p class="category__title"><?= $item['name'] ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>
