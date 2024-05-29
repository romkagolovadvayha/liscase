<?php

use common\models\servers\Servers;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */

$fishing = [
    [
        'name'  => 'Анчоус',
        'count' => $player['anchovy'],
        'score' => 10,
        'image' => '/images/fish/fish.anchovy.128.webp',
    ],
    [
        'name'  => 'Сом',
        'count' => $player['catfish'],
        'score' => 32,
        'image' => '/images/fish/fish.catfish.128.webp',
    ],
    [
        'name'  => 'Cельдь',
        'count' => $player['herring'],
        'score' => 10,
        'image' => '/images/fish/fish.herring.128.webp',
    ],
    [
        'name'  => 'Большеголов',
        'count' => $player['orangeroughy'],
        'score' => 37,
        'image' => '/images/fish/fish.orangeroughy.128.webp',
    ],
    [
        'name'  => 'Лосось',
        'count' => $player['salmon'],
        'score' => 22,
        'image' => '/images/fish/fish.salmon.128.webp',
    ],
    [
        'name'  => 'Сардина',
        'count' => $player['sardine'],
        'score' => 10,
        'image' => '/images/fish/fish.sardine.128.webp',
    ],
    [
        'name'  => 'Акула',
        'count' => $player['smallshark'],
        'score' => 45,
        'image' => '/images/fish/fish.smallshark.128.webp',
    ],
    [
        'name'  => 'Форель',
        'count' => $player['troutsmall'],
        'score' => 15,
        'image' => '/images/fish/fish.troutsmall.128.webp',
    ],
    [
        'name'  => 'Окунь',
        'count' => $player['yellowperch'],
        'score' => 25,
        'image' => '/images/fish/fish.yellowperch.128.webp',
    ],
];
usort(
    $fishing,
    function ($a, $b) {
        return ($b['score'] - $a['score']);
    }
);

?>
<div class="stats_player_stats_wrap stats_player_stats_wrap_fishing">
    <div class="stats_player_stats">
        <?php foreach ($fishing as $item): ?>
            <div class="stats_player_stats_item_wrap">
                <div class="stats_player_stats_item">
                    <div class="stats_player_stats_item_image_wrap">
                        <img class="stats_player_stats_item_image" src="<?= $item['image'] ?>"/>
                    </div>
                    <div class="stats_player_stats_item_count"><?= $item['count'] ?></div>
                    <div class="stats_player_stats_item_name"><?= $item['name'] ?></div>
                    <div class="stats_player_stats_item_score">x<?= $item['score'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
