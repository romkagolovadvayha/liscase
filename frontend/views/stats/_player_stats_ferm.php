<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */

$items = [
    ['key' => 'gathered_cloth', 'name' => Yii::t('common', 'Ткань')],
    ['key' => 'gathered_pumpkin', 'name' => Yii::t('common', 'Тыква')],
    ['key' => 'gathered_corn', 'name' => Yii::t('common', 'Кукуруза')],
    ['key' => 'gathered_potato', 'name' => Yii::t('common', 'Картофель')],
    ['key' => 'gathered_blue.berry', 'name' => Yii::t('common', 'Синие ягоды')],
    ['key' => 'gathered_yellow.berry', 'name' => Yii::t('common', 'Желтые ягоды')],
    ['key' => 'gathered_red.berry', 'name' => Yii::t('common', 'Красные ягоды')],
    ['key' => 'gathered_white.berry', 'name' => Yii::t('common', 'Белые ягоды')],
    ['key' => 'gathered_green.berry', 'name' => Yii::t('common', 'Зеленые ягоды')],
    ['key' => 'gathered_black.berry', 'name' => Yii::t('common', 'Черные ягоды')],
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

$fermers = [];
foreach ($items as $item) {
    $fermers[] = Statistics::getFermItem($drops, $player, $item['key'], $item['name']);
}

/*usort(
    $fermers,
    function ($a, $b) {
        return ($b['count'] - $a['count']);
    }
);*/

?>
<div class="stats_player_stats_wrap stats_player_stats_wrap_ferm">
    <h2><?=Yii::t('common', 'Фермерство')?></h2>
    <div class="stats_player_stats">
        <?php foreach ($fermers as $item): ?>
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
