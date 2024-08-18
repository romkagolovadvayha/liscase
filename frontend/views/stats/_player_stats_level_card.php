<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */

$items = [
    ['key' => 'card_level_3'],
    ['key' => 'card_level_2'],
    ['key' => 'card_level_1'],
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

$data = [];
foreach ($items as $item) {
    $data[] = Statistics::getLevelCardItem($drops, $player, $item['key']);
}

/*usort(
    $fermers,
    function ($a, $b) {
        return ($b['count'] - $a['count']);
    }
);*/

?>
<div class="stats_player_stats_wrap stats_player_stats_wrap_level_card">
    <h2><?=Yii::t('common', 'Использование карт доступа')?></h2>
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
