<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */

$keys = Statistics::find()
    ->cache(300)
    ->select('DISTINCT(`key`) as type')
    ->andWhere(['LIKE', 'key', '%mod_%', false])
    ->asArray()
    ->indexBy('type')
    ->all();

$keys = array_keys($keys);

$keyDrops = [];
foreach ($keys as $key) {
    if (strpos($key, 'tea') === false) {
        continue;
    }
    $keyDrops[] = str_replace('mod_', '', $key);
}

$drops = \common\models\box\Drop::find()
                                ->cache(300)
                                ->andWhere(['IN', 'eng_name', $keyDrops])
                                ->indexBy('eng_name')
                                ->all();
$items = [];
foreach ($keyDrops as $key) {
    $items[] = Statistics::getFoodItem($drops, $player, $key);
}

usort(
    $items,
    function ($a, $b) {
        return ($b['count'] - $a['count']);
    }
);


$items = array_slice($items, 0, 24);

?>
<div class="stats_player_stats_wrap stats_player_stats_wrap_tea">
    <h2><?=Yii::t('common', 'Чаепитие')?></h2>
    <div class="stats_player_stats">
        <?php foreach ($items as $item): ?>
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
