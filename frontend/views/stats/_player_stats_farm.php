<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */

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
        'score' => 0.2,
    ],
    [
        'name'  => Yii::t('common', 'Животный жир'),
        'key' => 'fat.animal',
        'score' => 0.2,
    ],
    [
        'name'  => Yii::t('common', 'Кожа'),
        'key' => 'leather',
        'score' => 0.2,
    ],
    [
        'name'  => Yii::t('common', 'Обломки костей'),
        'key' => 'bone.fragments',
        'score' => 0.1,
    ],
    [
        'name'  => Yii::t('common', 'Скрап'),
        'key' => 'scrap',
        'score' => 1.5,
    ],
    [
        'name'  => Yii::t('common', 'Разбито бочек'),
        'key' => 'barrel',
        'score' => 0,
    ],
    [
        'name'  => Yii::t('common', 'Открыто ящиков'),
        'key' => 'crate_open',
        'score' => 0,
    ],
];

$keys = [];
foreach ($items as $item) {
    $keys[] = $item['key'];
}

$drops = \common\models\box\Drop::find()
                                ->cache(60*60)
                                ->andWhere(['IN', 'eng_name', $keys])
                                ->indexBy('eng_name')
                                ->all();

$farms = [];
foreach ($items as $item) {
    $farms[] = Statistics::getFarmItem($drops, $player, $item['key'], $item['name'], $item['score']);
}
$labels = [Yii::t('common', 'Серная руда'), Yii::t('common', 'Железная руда'), Yii::t('common', 'Камни'), Yii::t('common', 'Дерево')];
$series = [Statistics::getParam($player, "sulfur.ore"), Statistics::getParam($player, "metal.ore"), Statistics::getParam($player, "stones"), Statistics::getParam($player, "wood")];
$labelsStr = '\'' . implode('\',\'', $labels) . '\'';
$seriesStr = '[' . implode('],[', $series) . ']';
$formatJs = <<< JS
var data = {
  labels: [{$labelsStr}],
  series: [{$seriesStr}]
};

// Устанавливаем несколько опцией, меняя настройки по умолчанию
var options = {
  seriesBarDistance: 60
};

new Chartist.Bar('#chart-resources', data, options);
JS;
$this->registerJs($formatJs, \yii\web\View::POS_END);

$labels = [Yii::t('common', 'Бочки'), Yii::t('common', 'Ящики')];
$series = [Statistics::getParam($player, "barrel"), Statistics::getParam($player, "crate_open")];
$labelsStr = '\'' . implode('\',\'', $labels) . '\'';
$seriesStr = implode(',', $series);
$formatJs = <<< JS
var data = {
  labels: [{$labelsStr}],
  series: [{$seriesStr}]
};

// Устанавливаем несколько опцией, меняя настройки по умолчанию
var options = {
  seriesBarDistance: 120
};

new Chartist.Pie('#chart-barrel', data, options);
JS;
$this->registerJs($formatJs, \yii\web\View::POS_END);
?>
<div class="stats_player_stats_farm_wrap">
    <div class="stats_player_stats_farm">
        <?php foreach ($farms as $item): ?>
            <div class="stats_player_stats_farm_item_wrap">
                <div class="stats_player_stats_farm_item">
                    <div class="stats_player_stats_farm_item_image_wrap">
                        <img class="stats_player_stats_farm_item_image" src="<?= $item['image'] ?>"/>
                    </div>
                    <div class="stats_player_stats_farm_item_count">
                        <span><?= $item['desc'] ?></span>
                        <?php if (!empty($item['score'])): ?>
                        <div class="stats_player_stats_farm_item_count_score"
                             data-bs-toggle="tooltip"
                             data-bs-placement="bottom"
                             data-bs-title="<?=Yii::t('common', 'Множитель для рейтинга игроков') . " x" . $item['score']?>">x<?= $item['score'] ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="stats_player_stats_farm_item_name"><?= Yii::t('database', $item['name']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="stats_player_stats_farm_charts">
        <div class="ct-chart ct-perfect-fourth" id="chart-resources"></div>
        <div class="ct-chart ct-perfect-fourth" id="chart-barrel"></div>
    </div>
</div>
