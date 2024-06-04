<?php

use common\models\servers\Servers;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */

$reider  = [
    [
        'name'  => 'C4',
        'icon'  => 'fa-solid fa-explosion',
        'image' => 'https://gamestores.app/img/games/rust/1248356124.png',
        'count' => $player['c4thrown'],
        'score' => 1,
        'desc'  => $player['c4thrown'],
    ],
    [
        'name'  => Yii::t('common', 'Ракеты'),
        'icon'  => 'fa-solid fa-rocket',
        'image' => 'https://gamestores.app/img/games/rust/-742865266.png',
        'count' => $player['rocketsfired'],
        'score' => 0.5,
        'desc'  => $player['rocketsfired'],
    ],
    [
        'name'  => Yii::t('common', 'Сачели'),
        'icon'  => 'fa-solid fa-bomb',
        'image' => 'https://gamestores.app/img/games/rust/-1878475007.png',
        'count' => $player['satchelsthrown'],
        'score' => 0.2,
        'desc'  => $player['satchelsthrown'],
    ],
];

?>
<div class="stats_player_stats_wrap stats_player_stats_wrap_reider">
    <div class="stats_player_stats">
        <?php foreach ($reider as $item): ?>
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
