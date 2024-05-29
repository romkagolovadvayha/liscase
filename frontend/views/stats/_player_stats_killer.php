<?php

use common\models\servers\Servers;

/** @var Servers $server */
/** @var array $data */
/** @var array $player */


$weapons = [
    [
        'weapon' => '/images/weapons/assultrifle.png',
        'name'   => 'Assult Rifle',
        'count'  => $player['ak47'],
    ],
    [
        'weapon' => '/images/weapons/lr300.png',
        'name'   => 'LR-300',
        'count'  => $player['lr300'],
    ],
    [
        'weapon' => '/images/weapons/sar.png',
        'name'   => 'Semi Auto Rifle',
        'count'  => $player['sar'],
    ],
    [
        'weapon' => '/images/weapons/hmlmg.png',
        'name'   => 'HM-LMG',
        'count'  => $player['hmlmg'],
    ],
    [
        'weapon' => '/images/weapons/m249.png',
        'name'   => 'M249',
        'count'  => $player['m249'],
    ],
    [
        'weapon' => '/images/weapons/bolt.png',
        'name'   => 'Bolt Action Rifle',
        'count'  => $player['bolt'],
    ],
    [
        'weapon' => '/images/weapons/l96.png',
        'name'   => 'L96',
        'count'  => $player['l96'],
    ],
    [
        'weapon' => '/images/weapons/mp5.png',
        'name'   => 'MP5',
        'count'  => $player['mp5'],
    ],
    [
        'weapon' => '/images/weapons/thompson.png',
        'name'   => 'Thompson',
        'count'  => $player['thompson'],
    ],
    [
        'weapon' => '/images/weapons/custom.png',
        'name'   => 'Custom SMG',
        'count'  => $player['custom'],
    ],
    [
        'weapon' => '/images/weapons/pumpshotgun.png',
        'name'   => 'Pump Shotgun',
        'count'  => $player['pump'],
    ],
    [
        'weapon' => '/images/weapons/double.png',
        'name'   => 'Double Barrel',
        'count'  => $player['doublebarrel'],
    ],
    [
        'weapon' => '/images/weapons/spaz12.png',
        'name'   => 'Spaz-12',
        'count'  => $player['spaz12'],
    ],
    [
        'weapon' => '/images/weapons/m92.png',
        'name'   => 'M92',
        'count'  => $player['m92'],
    ],
    [
        'weapon' => '/images/weapons/python.png',
        'name'   => 'Python',
        'count'  => $player['python'],
    ],
    [
        'weapon' => '/images/weapons/p250.png',
        'name'   => 'Semi Auto Pistol',
        'count'  => $player['semipistol'],
    ],
    [
        'weapon' => '/images/weapons/revolver.png',
        'name'   => 'Revolver',
        'count'  => $player['revolver'],
    ],
    [
        'weapon' => '/images/weapons/waterpipe.png',
        'name'   => 'Waterpipe',
        'count'  => $player['waterpipe'],
    ],
    [
        'weapon' => '/images/weapons/eoka.png',
        'name'   => 'Eoka',
        'count'  => $player['eoka'],
    ],
    [
        'weapon' => '/images/weapons/nailgun.png',
        'name'   => 'Nailgun',
        'count'  => $player['nailgun'] ?? 0,
    ],
    [
        'weapon' => '/images/weapons/compound.png',
        'name'   => 'Compound Bow',
        'count'  => $player['compound'],
    ],
    [
        'weapon' => '/images/weapons/crossbow.png',
        'name'   => 'Crossbow',
        'count'  => $player['crossbow'],
    ],
    [
        'weapon' => '/images/weapons/bow.png',
        'name'   => 'Hunting Bow',
        'count'  => $player['bow'],
    ],
];
usort(
    $weapons,
    function ($a, $b) {
        return ($b['count'] - $a['count']);
    }
);

$weapons = array_slice($weapons, 0, 10);
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
