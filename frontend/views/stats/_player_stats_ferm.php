<?php

use common\models\servers\Servers;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */

$fermers = [
    [
        'name'  => Yii::t('common', 'Ткань'),
        'image' => '/uploads/drop/505_d19c5f3eeb5235ed419e4a5771adee0d.png',
        'count' => $player['cloth'],
        'desc'  => number_format($player['cloth'], 0),
    ],
    [
        'name'  => Yii::t('common', 'Тыква'),
        'image' => 'https://gamestores.app/img/games/rust/-567909622.png',
        'count' => $player['pumpkin'],
        'desc'  => number_format($player['pumpkin'], 0),
    ],
    [
        'name'  => Yii::t('common', 'Кукуруза'),
        'image' => 'https://gamestores.app/img/games/rust/1367190888.png',
        'count' => $player['corn'],
        'desc'  => number_format($player['corn'], 0),
    ],
    [
        'name'  => Yii::t('common', 'Синие ягоды'),
        'image' => 'https://gamestores.app/img/games/rust/1112162468.png',
        'count' => $player['blue_berry'],
        'desc'  => number_format($player['blue_berry'], 0),
    ],
    [
        'name'  => Yii::t('common', 'Желтые ягоды'),
        'image' => 'https://gamestores.app/img/games/rust/1660145984.png',
        'count' => $player['yellow_berry'],
        'desc'  => number_format($player['yellow_berry'], 0),
    ],
    [
        'name'  => Yii::t('common', 'Красные ягоды'),
        'image' => 'https://gamestores.app/img/games/rust/1272194103.png',
        'count' => $player['red_berry'],
        'desc'  => number_format($player['red_berry'], 0),
    ],
    [
        'name'  => Yii::t('common', 'Белые ягоды'),
        'image' => 'https://gamestores.app/img/games/rust/854447607.png',
        'count' => $player['white_berry'],
        'desc'  => number_format($player['white_berry'], 0),
    ],
    [
        'name'  => Yii::t('common', 'Зеленые ягоды'),
        'image' => 'https://gamestores.app/img/games/rust/858486327.png',
        'count' => $player['green_berry'],
        'desc'  => number_format($player['green_berry'], 0),
    ],
    [
        'name'  => Yii::t('common', 'Картофель'),
        'image' => 'https://gamestores.app/img/games/rust/-2086926071.png',
        'count' => $player['potato'],
        'desc'  => number_format($player['potato'], 0),
    ],
];
usort(
    $fermers,
    function ($a, $b) {
        return ($b['count'] - $a['count']);
    }
);

?>
<div class="stats_player_stats_wrap stats_player_stats_wrap_ferm">
    <div class="stats_player_stats">
        <?php foreach ($fermers as $item): ?>
            <div class="stats_player_stats_item_wrap">
                <div class="stats_player_stats_item">
                    <div class="stats_player_stats_item_image_wrap">
                        <img class="stats_player_stats_item_image" src="<?= $item['image'] ?>"/>
                    </div>
                    <div class="stats_player_stats_item_count"><?= $item['desc'] ?></div>
                    <div class="stats_player_stats_item_name"><?= $item['name'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
