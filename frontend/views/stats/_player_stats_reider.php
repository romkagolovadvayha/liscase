<?php

use common\models\servers\Servers;
use common\models\statistics\Kills;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */
/** @var string $steamId */
/** @var string $wipeDate */
/** @var \common\models\user\User $user */

$items = [
    ['key' => 'c4thrown', 'score' => 1],
    ['key' => 'satchelsthrown', 'score' => 0.2],
    ['key' => 'rocket_basic', 'score' => 0.5],
    ['key' => 'rocket_hv', 'score' => 0.1],
    ['key' => 'rocket_fire', 'score' => 0.1],
    ['key' => 'ammo_explosive', 'score' => 0.01],
    ['key' => 'grenade.f1.deployed', 'score' => 0.05],
    ['key' => 'grenade.molotov.deployed', 'score' => 0.05],
    ['key' => 'grenade.beancan.deployed', 'score' => 0.05],
];

$keys = [];
foreach ($items as $item) {
    $keys[] = str_replace('.deployed', '', $item['key']);
}

$drops = \common\models\box\Drop::find()
                                ->cache(60*60)
                                ->andWhere(['IN', 'eng_name', $keys])
                                ->indexBy('eng_name')
                                ->all();

$reider = [];
foreach ($items as $item) {
    $reider[] = Statistics::getRaiderItem($drops, $player, $item['key'], $item['score']);
}
$countKillsData = Kills::find()
               ->cache(60*3)
               ->select(['COUNT(*) as count', 'DATE_FORMAT(created_at, "%H") as hour'])
               ->andWhere(['!=', 'dead', ''])
               ->andWhere(['steam_id' => $steamId])
               ->orderBy(['id' => SORT_DESC])
               ->asArray()
               ->indexBy('hour')
               ->groupBy(['hour'])
               ->all();
$countDeadsData = Kills::find()
               ->cache(60*30)
               ->select(['COUNT(*) as count', 'DATE_FORMAT(created_at, "%H") as hour'])
               ->andWhere(['dead' => $steamId])
               ->orderBy(['id' => SORT_DESC])
               ->asArray()
               ->indexBy('hour')
               ->groupBy(['hour'])
               ->all();

$maxHour = ['count' => 0, 'hour' => 0];
foreach ($countKillsData as $item) {
    if ($item['count'] > $maxHour['count']) {
        $maxHour['count'] = $item['count'];
        $maxHour['hour'] = $item['hour'];
    }
}

$dataKills = [];
$dataDeads = [];
for ($i = 0; $i <= 22; $i += 3) {
    if (empty($dataKills[$i])) {
        $dataKills[$i] = 0;
    }
    if (empty($dataDeads[$i])) {
        $dataDeads[$i] = 0;
    }
    if (!empty($countKillsData[$i])) {
        $dataKills[$i] += $countKillsData[$i]['count'];
    }
    if (!empty($countKillsData[$i+1])) {
        $dataKills[$i] += $countKillsData[$i+1]['count'];
    }
    if (!empty($countKillsData[$i+2])) {
        $dataKills[$i] += $countKillsData[$i+2]['count'];
    }
    if (!empty($countDeadsData[$i])) {
        $dataDeads[$i] += $countDeadsData[$i]['count'];
    }
    if (!empty($countDeadsData[$i+1])) {
        $dataDeads[$i] += $countDeadsData[$i+1]['count'];
    }
    if (!empty($countDeadsData[$i+2])) {
        $dataDeads[$i] += $countDeadsData[$i+2]['count'];
    }
}
$dataKillsStr = implode(',', $dataKills);
$dataDeadsStr = implode(',', $dataDeads);
$dataHoursStr = implode(',', array_keys($dataDeads));
$formatJs = <<< JS
var data = {
  labels: [{$dataHoursStr}],
  series: [
    [{$dataKillsStr}],
    [{$dataDeadsStr}],
  ]
};

// Устанавливаем несколько опцией, меняя настройки по умолчанию
var options = {
    low: 0,
    showArea: true,
  axisY: {
    labelInterpolationFnc: function(value) {
      return parseInt(value);
    }
  },
  axisX: {
    labelInterpolationFnc: function(value) {
      return value + " ч.";
    }
  }
};

new Chartist.Line('#chart-active', data, options);
JS;
$this->registerJs($formatJs, \yii\web\View::POS_END);
?>
<div class="stats_player_stats_reider_wrap">
    <div class="stats_player_stats_reider">
        <?php foreach ($reider as $item): ?>
            <div class="stats_player_stats_reider_item_wrap">
                <div class="stats_player_stats_reider_item">
                    <div class="stats_player_stats_reider_item_image_wrap">
                        <img class="stats_player_stats_reider_item_image" src="<?= $item['image'] ?>"/>
                    </div>
                    <div class="stats_player_stats_reider_item_count">
                        <span><?= $item['desc'] ?></span>
                        <div class="stats_player_stats_reider_item_count_score"
                             data-bs-toggle="tooltip"
                             data-bs-placement="bottom"
                             data-bs-title="<?=Yii::t('common', 'Множитель для рейтинга игроков') . " x" . $item['score']?>">x<?= $item['score'] ?></div>
                    </div>
                    <div class="stats_player_stats_reider_item_name"><?= $item['name'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="stats_player_stats_reider_kills_wrap">
        <div class="ct-chart ct-perfect-fourth" id="chart-active"></div>
        <div class="stats_player_stats_reider_kills_info">
            <div class="stats_player_stats_reider_kills_info_item">
                <div class="stats_player_stats_reider_kills_info_item_count"><?=\common\components\helpers\DateHelper::numberFormat($maxHour['hour'], 2) . ":00"?></div>
                <div class="stats_player_stats_reider_kills_info_item_value"><?=Yii::t('common', 'Наибольшая активность')?></div>
            </div>
            <div class="stats_player_stats_reider_kills_info_item">
                <div class="stats_player_stats_reider_kills_info_item_count"><?=Statistics::getParam($player, 'tcsdestroyed')?></div>
                <div class="stats_player_stats_reider_kills_info_item_value"><?=Yii::t('common', 'Зарейдено шкафов')?></div>
            </div>
            <div class="stats_player_stats_reider_kills_info_item">
                <div class="stats_player_stats_reider_kills_info_item_count"><?=Statistics::getParam($player, 'wounded')?></div>
                <div class="stats_player_stats_reider_kills_info_item_value"><?=Yii::t('common', 'Нокнули')?></div>
            </div>
            <div class="stats_player_stats_reider_kills_info_item">
                <div class="stats_player_stats_reider_kills_info_item_count"><?=Statistics::getParam($player, 'scientists')?></div>
                <div class="stats_player_stats_reider_kills_info_item_value"><?=Yii::t('common', 'Убито ботов')?></div>
            </div>
            <div class="stats_player_stats_reider_kills_info_item">
                <div class="stats_player_stats_reider_kills_info_item_count"><?=Statistics::getParam($player, 'helicopters')?></div>
                <div class="stats_player_stats_reider_kills_info_item_value"><?=Yii::t('common', 'Уничтожено вертолетов')?></div>
            </div>
            <div class="stats_player_stats_reider_kills_info_item">
                <div class="stats_player_stats_reider_kills_info_item_count"><?=Statistics::getParam($player, 'bradleys')?></div>
                <div class="stats_player_stats_reider_kills_info_item_value"><?=Yii::t('common', 'Уничтожено танков')?></div>
            </div>
        </div>
        <?php if ($this->beginCache('_player_kills2_' . $user->id . $server->id . Yii::$app->language, ['duration' => 30])): ?>
            <?=$this->render('_player_kills2', [
                'user' => $user,
                'server' => $server,
                'title' => Yii::t('common', 'История убийств'),
            ]);?>
            <?php $this->endCache(); ?>
        <?php endif; ?>
    </div>
</div>
