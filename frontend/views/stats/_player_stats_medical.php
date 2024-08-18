<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */

$items = [
    ['key' => 'largemedkit'],
    ['key' => 'syringe'],
    ['key' => 'bandage'],
];

$keys = [];
foreach ($items as $item) {
    $keys[] = $item['key'];
}

$drops = \common\models\box\Drop::find()
                                ->andWhere(['IN', 'eng_name', $keys])
                                ->indexBy('eng_name')
                                ->all();

$data = [];
foreach ($items as $item) {
    $data[] = Statistics::getLevelCardItem($drops, $player, $item['key']);
}

?>
<div class="stats_player_stats_wrap stats_player_stats_wrap_medical">
    <h2><?=Yii::t('common', 'Медицина')?></h2>
    <div class="stats_player_stats">
        <?php foreach ($data as $item): ?>
            <div class="stats_player_stats_item_wrap">
                <div class="stats_player_stats_item">
                    <div class="stats_player_stats_item_image_wrap">
                        <img class="stats_player_stats_item_image" src="<?= $item['image'] ?>"/>
                    </div>
                    <div class="stats_player_stats_item_count_wrap">
                        <div class="stats_player_stats_item_count"><?= $item['desc'] ?></div>
                        <div class="stats_player_stats_item_name"><?= $item['name'] ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
