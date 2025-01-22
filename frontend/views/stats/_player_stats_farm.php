<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $images */
/** @var array $names */
/** @var array $player */
/** @var string $steamId */
/** @var \common\models\user\User $user */

$items   = [
    [
        'name'  => Yii::t('common', 'Серная руда'),
        'key' => 'sulfur.ore',
        'score' => 1,
    ],
    [
        'name'  => Yii::t('common', 'Железная руда'),
        'key' => 'metal.ore',
        'score' => 0.5,
    ],
    [
        'name'  => Yii::t('common', 'Камни'),
        'key' => 'stones',
        'score' => 0.3,
    ],
    [
        'name'  => Yii::t('common', 'Дерево'),
        'key' => 'wood',
        'score' => 0.05,
    ],
    [
        'name'  => Yii::t('common', 'Животный жир'),
        'key' => 'fat.animal',
        'score' => 0,
    ],
    [
        'name'  => Yii::t('common', 'Кожа'),
        'key' => 'leather',
        'score' => 0,
    ],
    [
        'name'  => Yii::t('common', 'Обломки костей'),
        'key' => 'bone.fragments',
        'score' => 0,
    ],
    [
        'name'  => Yii::t('common', 'Скрап'),
        'key' => 'scrap',
        'score' => 0,
    ],
];

$farms = [];
foreach ($items as $item) {
    $farms[] = Statistics::getFarmItem($images, $names, $player, $item['key'], $item['name'], $item['score']);
}
?>

<!-- Ресурсы -->
<section class="page-stats__block-without-hover">
    <header class="flex items-center justify-space-between mb-24 transition-all">
        <h4 class="flex items-center gap-x-12">
            <?=Yii::t('common', 'Ресурсы')?><span
                    class="icons icons_24px icons_24px_info icons_hover"
                    data-bs-toggle="tooltip"
                    data-bs-placement="right"
                    data-bs-title="<?=Yii::t('common', 'Количество нафармленных ресурсов игроком за вайп')?>"
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
        <?php foreach ($farms as $item): ?>
            <div class="page-stats__category category">
                <h5 class="category__count-and-img">
                    <span><?= $item['desc'] ?>
                        <?php if (!empty($item['score'])): ?>
                        <span class="category__x"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-placement="bottom"
                                                    data-bs-title="<?=Yii::t('common', 'Множитель для рейтинга игроков') . " x" . $item['score']?>">x<?= $item['score'] ?></span>
                        <?php endif; ?>
                    </span>
                    <img src="<?= $item['image'] ?>" alt="<?= Yii::t('database', $item['name']) ?>" class="w-64 h-64 object-contain" />
                </h5>
                <p class="category__title"><?= Yii::t('database', $item['name']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>