<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */

$items = [
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

$keys = [];
foreach ($items as $item) {
    $keys[] = $item['key'];
}

$drops = \common\models\box\Drop::find()
                                ->cache(300)
                                ->andWhere(['IN', 'eng_name', $keys])
                                ->indexBy('eng_name')
                                ->all();

$fishing = [];
foreach ($items as $item) {
    $fishing[] = Statistics::getFishItem($drops, $player, $item['key'], $item['name'], $item['score']);
}

usort(
    $fishing,
    function ($a, $b) {
        return ($b['score'] - $a['score']);
    }
);

?>
<div class="stats_player_stats_wrap stats_player_stats_wrap_fishing">
    <h2><?=Yii::t('common', 'Рыбаловство')?></h2>
    <div class="stats_player_stats">
        <?php foreach ($fishing as $item): ?>
            <div class="stats_player_stats_item_wrap">
                <div class="stats_player_stats_item">
                    <div class="stats_player_stats_item_image_wrap">
                        <img class="stats_player_stats_item_image" src="<?= $item['image'] ?>"/>
                    </div>
                    <div class="stats_player_stats_item_count_wrap">
                        <div class="stats_player_stats_item_count"><?= $item['count'] ?></div>
                        <div class="stats_player_stats_item_name"><?= $item['name'] ?></div>
                        <div class="stats_player_stats_item_score">x<?= $item['score'] ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
