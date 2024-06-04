<?php

use common\models\servers\Servers;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */


$hunters = [
    [
        'name'  => Yii::t('common', 'Курицы'),
        'count' => $player['chickens'],
        'icon'  => 'fa-solid fa-egg',
    ],
    [
        'name'  => Yii::t('common', 'Кабаны'),
        'count' => $player['boars'],
        'icon'  => 'fa-solid fa-hippo',
    ],
    [
        'name'  => Yii::t('common', 'Олени'),
        'count' => $player['deers'],
        'icon'  => 'fa-solid fa-leaf',
    ],
    [
        'name'  => Yii::t('common', 'Лошади'),
        'count' => $player['horses'],
        'icon'  => 'fa-solid fa-horse',
    ],
    [
        'name'  => Yii::t('common', 'Волки'),
        'count' => $player['wolves'],
        'icon'  => 'fa-solid fa-bone',
    ],
    [
        'name'  => Yii::t('common', 'Медведи'),
        'count' => $player['bears'],
        'icon'  => 'fa-solid fa-paw',
    ],
    [
        'name'  => Yii::t('common', 'Ученые'),
        'count' => $player['scientists'],
        'icon'  => 'fa-solid fa-walkie-talkie',
    ],
    [
        'name'  => Yii::t('common', 'Вертолеты'),
        'count' => $player['helicopters'],
        'icon'  => 'fa-solid fa-helicopter',
    ],
    [
        'name'  => Yii::t('common', 'Танки'),
        'count' => $player['bradleys'],
        'icon'  => 'fa-solid fa-car-burst',
    ],
];
usort(
    $hunters,
    function ($a, $b) {
        return ($b['count'] - $a['count']);
    }
);

?>
<div class="stats_player_stats_npc_wrap">
    <div class="stats_player_stats_npc">
        <?php foreach ($hunters as $item): ?>
            <div class="stats_player_stats_npc_item_wrap">
                <div class="stats_player_stats_npc_item_header">
                    <i class="<?=$item['icon']?> profile-icon"></i><?=$item['name']?>
                </div>
                <div class="stats_player_stats_npc_item">
                    <div class="stats_player_stats_npc_item_count"><?=$item['count']?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
