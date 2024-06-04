<?php

use common\models\servers\Servers;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */

$farms   = [
    [
        'name'  => Yii::t('common', 'Серная руда'),
        'image' => '/uploads/drop/312_f1bbbd27a6a2da9e0f82b83ae1ac284a.png',
        'count' => $player['sulfur_ore'],
        'score' => 1,
        'desc'  => number_format($player['sulfur_ore'], 0),
    ],
    [
        'name'  => Yii::t('common', 'Железная руда'),
        'image' => '/uploads/drop/297_c8f416368524f23913bb208025e18f29.png',
        'count' => $player['metal_ore'],
        'score' => 0.5,
        'desc'  => number_format($player['metal_ore'], 0),
    ],
    [
        'name'  => Yii::t('common', 'Камни'),
        'image' => '/uploads/drop/300_2a1ca11dc57b4a22968d9a5aa8c21ec8.png',
        'count' => $player['stones'],
        'score' => 0.3,
        'desc'  => number_format($player['stones'], 0),
    ],
    [
        'name'  => Yii::t('common', 'Дерево'),
        'image' => '/uploads/drop/295_f43e705790003ee29b962e8ab921eb16.png',
        'count' => $player['wood'],
        'score' => 0.2,
        'desc'  => number_format($player['wood'], 0),
    ],
];

?>
<div class="stats_player_stats_wrap stats_player_stats_wrap_farm">
    <div class="stats_player_stats">
        <?php foreach ($farms as $item): ?>
            <div class="stats_player_stats_item_wrap">
                <div class="stats_player_stats_item">
                    <div class="stats_player_stats_item_image_wrap">
                        <img class="stats_player_stats_item_image" src="<?= $item['image'] ?>"/>
                    </div>
                    <div class="stats_player_stats_item_count"><?= $item['desc'] ?></div>
                    <div class="stats_player_stats_item_name"><?= $item['name'] ?></div>
                    <div class="stats_player_stats_item_score">x<?= $item['score'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
