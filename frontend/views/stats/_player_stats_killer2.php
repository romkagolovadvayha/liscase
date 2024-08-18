<?php

use common\models\servers\Servers;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */
/** @var string $steamId */
/** @var string $wipeDate */

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

?>
<div class="stats_player_stats_wrap">
    <div class="stats_player_stats">
        <?php foreach ($weapons as $weapon): ?>
            <div class="stats_player_stats_item_wrap">
                <div class="stats_player_stats_item">
                    <div class="stats_player_stats_item_image_wrap">
                        <img class="stats_player_stats_item_image" src="<?= $weapon['weapon'] ?>"/>
                    </div>
                    <div class="stats_player_stats_item_count"><?= $weapon['count'] ? $weapon['count'] : 0 ?></div>
                    <div class="stats_player_stats_item_name"><?= $weapon['name'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
