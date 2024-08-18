<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */

$kdr      = Statistics::getParam($player, 'deaths') > 0 ? round(Statistics::getParam($player, 'kills') / Statistics::getParam($player, 'deaths'), 2) : Statistics::getParam($player, 'kills');


$hunters = [
    [
        'name'  => Yii::t('common', 'Кабаны'),
        'count' => Statistics::getParam($player, 'boar'),
        'icon'  => 'fa-solid fa-hippo',
    ],
    [
        'name'  => Yii::t('common', 'Лошади'),
        'count' => Statistics::getParam($player, 'horse'),
        'icon'  => 'fa-solid fa-horse',
    ],
    [
        'name'  => Yii::t('common', 'Волки'),
        'count' => Statistics::getParam($player, 'wolf'),
        'icon'  => 'fa-solid fa-bone',
    ],
    [
        'name'  => Yii::t('common', 'Медведи'),
        'count' => Statistics::getParam($player, 'bear'),
        'icon'  => 'fa-solid fa-paw',
    ],
    [
        'name'  => Yii::t('common', 'Белые медведи'),
        'count' => Statistics::getParam($player, 'polarbear'),
        'icon'  => 'fa-solid fa-paw',
    ],
    [
        'name'  => Yii::t('common', 'Олени'),
        'count' => Statistics::getParam($player, 'deer'),
        'icon'  => 'fa-solid fa-leaf',
    ],
    [
        'name'  => Yii::t('common', 'Курицы'),
        'count' => Statistics::getParam($player, 'chicken'),
        'icon'  => 'fa-solid fa-egg',
    ],
];
//usort(
//    $hunters,
//    function ($a, $b) {
//        return ($b['count'] - $a['count']);
//    }
//);

?>
<div class="stats_player_stats_wrap stats_player_stats_wrap_hunter">
    <h2><?=Yii::t('common', 'Охота')?></h2>
    <div class="stats_player_stats">
        <?php foreach ($hunters as $item): ?>
            <div class="stats_player_stats_item_wrap">
                <div class="stats_player_stats_item">
                    <div class="stats_player_stats_item_count_wrap">
                        <div class="stats_player_stats_item_count"><?= $item['count'] ?></div>
                        <div class="stats_player_stats_item_name"><?= $item['name'] ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
