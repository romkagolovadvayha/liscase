<?php

use common\models\servers\Servers;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */

$reider  = [
    [
        'name'  => 'C4',
        'icon'  => 'fa-solid fa-explosion',
        'image' => '/uploads/drop/446_3da715bdfd205544821f8969040465c2.png',
        'count' => $player['c4thrown'],
        'score' => 1,
        'desc'  => $player['c4thrown'],
    ],
    [
        'name'  => Yii::t('common', 'Ракеты'),
        'icon'  => 'fa-solid fa-rocket',
        'image' => '/uploads/drop/602_0736169c589c86cc02ce3f46ad8edfd4.png',
        'count' => $player['rocketsfired'],
        'score' => 0.5,
        'desc'  => $player['rocketsfired'],
    ],
    [
        'name'  => Yii::t('common', 'Скоросная ракета'),
        'icon'  => 'fa-solid fa-rocket',
        'image' => '/uploads/drop/607_cd4b7fbcde2c213425120c4ab2c4bf66.png',
        'count' => $player['rocket_hv'],
        'score' => 0.1,
        'desc'  => $player['rocket_hv'],
    ],
    [
        'name'  => Yii::t('common', 'Зажигательная ракета'),
        'icon'  => 'fa-solid fa-rocket',
        'image' => '/uploads/drop/591_2ad1f7d1c19c240780a16862181d95f7.png',
        'count' => $player['rocket_fire'],
        'score' => 0.1,
        'desc'  => $player['rocket_fire'],
    ],
    [
        'name'  => Yii::t('common', 'Сачели'),
        'icon'  => 'fa-solid fa-bomb',
        'image' => '/uploads/drop/466_c87b342d9ba1762f554cb046c22714b2.png',
        'count' => $player['satchelsthrown'],
        'score' => 0.2,
        'desc'  => $player['satchelsthrown'],
    ],
    [
        'name'  => Yii::t('common', 'Разрывной патрон'),
        'icon'  => 'fa-solid fa-bomb',
        'image' => '/uploads/drop/597_d6ffba9907ea045784a149a8489bb716.png',
        'count' => $player['ammo_explosive'],
        'score' => 0.01,
        'desc'  => $player['ammo_explosive'],
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
