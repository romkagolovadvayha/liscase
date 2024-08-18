<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */
/** @var string $steamId */
/** @var string $wipeDate */

$kdr      = Statistics::getParam($player, 'deaths') > 0 ? round(Statistics::getParam($player, 'kills') / Statistics::getParam($player, 'deaths'), 2) : Statistics::getParam($player, 'kills');

$kills = \common\models\statistics\Kills::find()
                                        ->select(['weapon', 'COUNT(*) as count'])
                                        ->andWhere(['steam_id' => $steamId])
                                        ->andWhere(['server_tag' => $server->tag])
                                        ->andWhere(['wipe' => $wipeDate])
                                        ->asArray()
                                        ->groupBy('weapon')
                                        ->orderBy(['count' => SORT_DESC])
                                        ->all();

$weaponsList = [];
foreach ($kills as $item) {
    $weaponsList[] = $item['weapon'];
}

$drops = \common\models\box\Drop::find()
                                ->andWhere(['IN', 'eng_name', $weaponsList])
                                ->indexBy('eng_name')
                                ->all();

$weapons = [];
foreach ($kills as $item) {
    if (empty($item['weapon']) || empty($drops[$item['weapon']])) {
        continue;
    }
    $weapons[] = [
        'weapon' => $drops[$item['weapon']]->imageOrig->getImagePubUrl(),
        'name'   => $drops[$item['weapon']]->name,
        'count'  => $item['count'],
    ];
}

$hunters = [
    [
        'name'  => Yii::t('common', 'Убийств'),
        'icon'  => 'fa-solid fa-gun',
        'count' => Statistics::getParam($player, 'kills'),
    ],
    [
        'name'  => Yii::t('common', 'Смертей'),
        'icon'  => 'fa-solid fa-cross',
        'count' => Statistics::getParam($player, 'deaths'),
    ],
    [
        'name'  => 'K/D',
        'icon'  => 'fa-solid fa-crosshairs',
        'count' => $kdr,
    ],
];
//usort(
//    $hunters,
//    function ($a, $b) {
//        return ($b['count'] - $a['count']);
//    }
//);
$kills = Statistics::getParam($player, 'kills');
$deaths = Statistics::getParam($player, 'deaths');
$formatJs = <<< JS
var data = {
  labels: ["Убийств", "Смертей"],
    series: [
    [{$kills}],
    [{$deaths}]
  ]
};

var options = {
  seriesBarDistance: 120
};

new Chartist.Bar('#chart-cd', data, options);
JS;
$this->registerJs($formatJs, \yii\web\View::POS_END);
?>
<div class="stats_player_stats_killer_wrap">
    <div class="stats_player_stats_killer">
        <?php foreach ($hunters as $item): ?>
            <div class="stats_player_stats_killer_item_wrap">
                <div class="stats_player_stats_killer_item_header">
                    <?=$item['name']?>
                </div>
                <div class="stats_player_stats_killer_item">
                    <div class="stats_player_stats_killer_item_count"><?=$item['count']?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="ct-chart ct-perfect-fourth" id="chart-cd"></div>
    <div class="stats_player_stats_killer_hits">
<!--        <img class="stats_player_stats_killer_hits_image" src="/images/artic_hazmat.png"/>-->
        <img class="stats_player_stats_killer_hits_image" src="/images/player2.png"/>
        <div class="stats_player_stats_killer_hits_info"><?=Yii::t('common', 'Наведите курсор для информации по выстрелам')?></div>
        <div class="stats_player_stats_killer_hits_hits">
            <div class="stats_player_stats_killer_hits_hits_item stats_player_stats_killer_hits_hits_head"><?=Statistics::getParam($player, 'hits_head')?></div>
            <div class="stats_player_stats_killer_hits_hits_item stats_player_stats_killer_hits_hits_neck"><?=Statistics::getParam($player, 'hits_neck')?></div>
            <div class="stats_player_stats_killer_hits_hits_item stats_player_stats_killer_hits_hits_chest"><?=Statistics::getParam($player, 'hits_chest')?></div>
            <div class="stats_player_stats_killer_hits_hits_item stats_player_stats_killer_hits_hits_lowerspine"><?=Statistics::getParam($player, 'hits_lowerspine')?></div>
            <div class="stats_player_stats_killer_hits_hits_item stats_player_stats_killer_hits_hits_leftleg"><?=Statistics::getParam($player, 'hits_leftleg')?></div>
            <div class="stats_player_stats_killer_hits_hits_item stats_player_stats_killer_hits_hits_rightleg"><?=Statistics::getParam($player, 'hits_rightleg')?></div>
            <div class="stats_player_stats_killer_hits_hits_item stats_player_stats_killer_hits_hits_leftfoot"><?=Statistics::getParam($player, 'hits_leftfoot')?></div>
            <div class="stats_player_stats_killer_hits_hits_item stats_player_stats_killer_hits_hits_rightfoot"><?=Statistics::getParam($player, 'hits_rightfoot')?></div>
            <div class="stats_player_stats_killer_hits_hits_item stats_player_stats_killer_hits_hits_lefthand"><?=Statistics::getParam($player, 'hits_lefthand')?></div>
            <div class="stats_player_stats_killer_hits_hits_item stats_player_stats_killer_hits_hits_righthand"><?=Statistics::getParam($player, 'hits_righthand')?></div>
        </div>
    </div>
    <div class="stats_player_stats_killer_weapons">
        <?php foreach ($weapons as $weapon): ?>
            <div class="stats_player_stats_killer_weapons_item_wrap"
                 data-bs-toggle="tooltip"
                 data-bs-placement="bottom"
                 data-bs-title="<?= $weapon['name'] ?>">
                <div class="stats_player_stats_killer_weapons_item">
                    <div class="stats_player_stats_killer_weapons_item_image_wrap">
                        <img class="stats_player_stats_killer_weapons_item_image" src="<?= $weapon['weapon'] ?>"/>
                    </div>
                    <div class="stats_player_stats_killer_weapons_item_count"><?= $weapon['count'] ? $weapon['count'] : 0 ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>