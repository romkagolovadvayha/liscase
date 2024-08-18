<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */

$farms   = [
    [
        'name'  => Yii::t('common', 'Открыто ящиков'),
        'image' => '/uploads/drop/441_522b6e2b6c99a6e2e596db57ef080be6.png',
        'count' => Statistics::getParam($player, 'crate_open'),
        'desc'  => number_format(Statistics::getParam($player, 'crate_open'), 0),
    ],
    [
        'name'  => Yii::t('common', 'Разбито бочек'),
        'image' => '/images/barrel.png',
        'count' => Statistics::getParam($player, 'barrel'),
        'desc'  => number_format(Statistics::getParam($player, 'barrel'), 0),
    ],
];

?>
<div class="stats_player_stats_wrap stats_player_stats_wrap_barrel">
    <div class="stats_player_stats">
        <?php foreach ($farms as $item): ?>
            <div class="stats_player_stats_item_wrap">
                <div class="stats_player_stats_item">
                    <div class="stats_player_stats_item_image_wrap">
                        <img class="stats_player_stats_item_image" src="<?= $item['image'] ?>"/>
                    </div>
                    <div class="stats_player_stats_item_count"><?= $item['desc'] ?></div>
                    <div class="stats_player_stats_item_name"><?= $item['name'] ?></div>
                    <?php if (!empty($item['score'])): ?>
                        <div class="stats_player_stats_item_score">x<?= $item['score'] ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
