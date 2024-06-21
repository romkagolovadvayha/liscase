<?php

/** @var $dbHost */
/** @var $dbUser */
/** @var $dbPassword */
/** @var $dbName */
/** @var $server */

$connection = mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName);
if($connection->connect_errno) {
    header('Location: /error.php?error=noconnection');
    exit();
}
if ($connection->connect_error) {
    header('Location: /error.php?error=noconnection');
    exit();
}

$table = "main_stats_kills";
$tableWipe = "main_stats_wipe";

$sql = "SELECT k.*, w.name
FROM $table k
LEFT JOIN $tableWipe w ON w.steamid = k.steam_id 
WHERE k.dead <> ''
ORDER BY id DESC
LIMIT 10
";

$scientists = [
    'default' => '/images/weapons/hazmatsuit_scientist.128.webp',
    //    'npc_tunneldweller' => '/assets/images/live/npc_tunneldweller.png',
    //    'npc_underwaterdweller' => '/assets/images/live/npc_underwaterdweller.png',
    'scientistnpc_heavy' => '/images/weapons/hazmatsuit_scientist_nvgm.128.webp',
];
$weapons = [
    'default' => '/images/weapons/assultrifle.png',
    'smg.mp5' => '/images/weapons/mp5.png',
    'smg.thompson' => '/images/weapons/thompson.png',
    'rifle.ak' => '/images/weapons/assultrifle.png',
    'rifle.ak.ice' => '/images/weapons/rifle.ak.ice.128.webp',
    'rifle.ak.diver' => '/images/weapons/rifle.ak.diver.128.webp',
    'bow.hunting' => '/images/weapons/bow.png',
    'bow.compound' => '/images/weapons/compound.png',
    'crossbow' => '/images/weapons/crossbow.png',
    'rifle.semiauto' => '/images/weapons/sar.png',
    'shotgun.pump' => '/images/weapons/pumpshotgun.png',
    'rifle.bolt' => '/images/weapons/bolt.png',
    'jackhammer' => '/images/weapons/Jackhammer.png',
    'hmlmg' => '/images/weapons/hmlmg.png',
    'shotgun.waterpipe' => '/images/weapons/waterpipe.png',
    'rifle.lr300' => '/images/weapons/lr300.png',
    'pistol.revolver' => '/images/weapons/revolver.png',
    'pistol.eoka' => '/images/weapons/eoka.png',
    'pistol.prototype17' => '/images/weapons/pistol.prototype17.128.webp',
    'pistol.m92' => '/images/weapons/m92.png',
    'pistol.nailgun' => '/images/weapons/nailgun.png',
    'pistol.python' => '/images/weapons/pistol.python.128.webp',
    'shotgun.double' => '/images/weapons/double.png',
    'shotgun.spas12' => '/images/weapons/spaz12.png',
    'lmg.m249' => '/images/weapons/m249.png',
    'smg.2' => '/images/weapons/custom.png',
    'rifle.l96' => '/images/weapons/l96.png',
    'rifle.m39' => '/images/weapons/lr300.png',
    'grenade.f1' => '/images/weapons/grenade.beancan.128.webp',
    'grenade.beancan' => '/images/weapons/grenade.beancan.128.webp',
    'bone.club' => '/images/weapons/bone.club.128.webp',
    'spear.stone' => '/uploads/drop/87_c3d5adbad17377b2bfc0a86147c51fa5.png',
    'mace' => '/images/weapons/mace.128.webp',
    'minigun' => '/images/weapons/minigun.128.webp',
    'multiplegrenadelauncher' => '/images/weapons/multiplegrenadelauncher.128.webp',
    'hammer' => '/images/weapons/hammer.128.webp',
    'rock' => '/images/weapons/rock.128.webp',
    'shotgun.m4' => '/images/weapons/shotgun.m4.128.webp',
    'spear.wooden' => '/images/weapons/spear.wooden.128.webp',
    'paddle' => '/images/weapons/paddle.128.webp',
    'hammer.salvaged' => '/images/weapons/hammer.salvaged.128.webp',
    'pickaxe' => '/images/weapons/pickaxe.128.webp',
    'hatchet' => '/images/weapons/hatchet.128.webp',
    'icepick.salvaged' => '/images/weapons/icepick.salvaged.128.webp',
    'pistol.semiauto' => '/images/weapons/p250.png',
    'salvaged.cleaver' => '/images/weapons/salvaged.cleaver.128.webp',
    'axe.salvaged' => '/images/weapons/axe.salvaged.128.webp',
    'torch' => '/images/weapons/torch.128.webp',
    'stone.pickaxe' => '/images/weapons/stone.pickaxe.128.webp',
    'knife.combat' => '/images/weapons/knife.combat.128.webp',
    'machete' => '/images/weapons/machete.128.webp',
    'salvaged.sword' => '/images/weapons/salvaged.sword.128.webp',
];
$animals = [
    'bear' => 'медведь',
    'polarbear' => 'белый медведь',
    'boar' => 'кабан',
    'chicken' => 'курица',
    'horse' => 'лошадь',
    'wolf' => 'волк',
    'stag' => 'олень',
    'autoturret_deployed' => 'турель',
];
$animals2 = [
    'bear' => 'медведя',
    'polarbear' => 'белого медведя',
    'boar' => 'кабана',
    'chicken' => 'курицу',
    'horse' => 'лошадь',
    'wolf' => 'волка',
    'stag' => 'оленя',
];

$data = mysqli_query($connection, $sql);
$results = [];
while($row = mysqli_fetch_assoc($data)) {
    if ($row['type'] !== 'deaths' && $row['type'] !== 'suicides') {
        if (!empty($weapons[$row['weapon']])) {
            $row['weapon_image'] = $weapons[$row['weapon']];
        } elseif (!empty($row['weapon'])) {
            $row['weapon_image'] = $weapons['default'];
        }
    }
    if ($row['type'] === 'scientists') {
        if (!empty($scientists[$row['dead']])) {
            $row['image'] = $scientists[$row['dead']];
        } else {
            $row['image'] = $scientists['default'];
        }
    }
    if ($row['type'] === 'kill') {
        if (strlen($row['steam_id']) < 10) {
            $row['image'] = $scientists['default'];
        }
        $sql2             = "SELECT w.name FROM $tableWipe w WHERE w.steamid = {$row['dead']}";
        $data2            = mysqli_query($connection, $sql2);
        $wipeUser         = mysqli_fetch_assoc($data2);
        if (empty($wipeUser['name'])) {
            continue;
        }
        $row['dead_name'] = $wipeUser['name'];
    }
    $results[] = $row;
}
?>

<?php foreach ($results as $row): ?>
    <div class="live_items_item">
        <?php if (!empty($row['weapon_image'])): ?>
            <img width="30px" title="<?=$row['weapon']?>" src="<?=$row['weapon_image']?>"/>
        <?php endif; ?>
        <?php if ($row['type'] === 'suicides'): ?>
            <a title="<?=Yii::t('common', 'Открыть профиль Steam')?>" class="link_name" target="#" href="/stats/player?steamId=<?=$row['steam_id']?>&server=<?=$server?>"><?=$row['name']?></a>
            <span><?=Yii::t('common', 'совершил самоубийство')?></span>
        <?php endif; ?>
        <?php if ($row['type'] === 'animal'): ?>
            <a title="<?=Yii::t('common', 'Открыть профиль Steam')?>" class="link_name" target="#" href="/stats/player?steamId=<?=$row['steam_id']?>&server=<?=$server?>"><?=$row['name']?></a>
            <span><?=Yii::t('common', 'убил')?></span>
            <span><?=$animals2[$row['dead']]?></span>
        <?php endif; ?>
        <?php if ($row['type'] === 'deaths'): ?>
            <span><?=$animals[$row['dead']]?></span>
            <span><?=Yii::t('common', 'убил')?></span>
            <a title="<?=Yii::t('common', 'Открыть профиль Steam')?>" class="link_name" target="#" href="/stats/player?steamId=<?=$row['steam_id']?>&server=<?=$server?>"><?=$row['name']?></a>
        <?php endif; ?>
        <?php if ($row['type'] === 'scientists'): ?>
            <a title="<?=Yii::t('common', 'Открыть профиль Steam')?>" class="link_name" target="#" href="/stats/player?steamId=<?=$row['steam_id']?>&server=<?=$server?>"><?=$row['name']?></a>
            <span><?=Yii::t('common', 'убил')?></span>
            <img width="30px" src="<?=$row['image']?>"/>
            <span><?=Yii::t('common', 'бота')?></span>
        <?php endif; ?>
        <?php if ($row['type'] === 'kill'): ?>
            <?php if (empty($row['image'])): ?>
                <a title="<?=Yii::t('common', 'Открыть профиль Steam')?>" class="link_name" target="#" href="/stats/player?steamId=<?=$row['steam_id']?>&server=<?=$server?>"><?=$row['name']?></a>
            <?php else: ?>
                <span><?=Yii::t('common', 'Бот')?></span>
                <img width="30px" src="<?=$row['image']?>"/>
            <?php endif; ?>
            <span><?=Yii::t('common', 'убил')?></span>
            <a title="<?=Yii::t('common', 'Открыть профиль Steam')?>" class="link_name" target="#" href="/stats/player?steamId=<?=$row['dead']?>&server=<?=$server?>"><?=$row['dead_name']?></a>
        <?php endif; ?>
    </div>
<?php endforeach; ?>