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

$table = "main_stats_wipe";
$sql = "SELECT * FROM `$table` WHERE steamid NOT IN (76561198394504608)  ORDER BY `kills` DESC LIMIT 1";
$res_data = mysqli_query($connection, $sql);
$killer = mysqli_fetch_assoc($res_data);

$sql = "SELECT * FROM `$table` WHERE steamid NOT IN (76561198394504608)  ORDER BY `deaths` DESC LIMIT 1";
$res_data = mysqli_query($connection, $sql);
$deaths = mysqli_fetch_assoc($res_data);

$sql = "SELECT * FROM `$table` WHERE steamid NOT IN (76561198394504608)  ORDER BY `scientists` DESC LIMIT 1";
$res_data = mysqli_query($connection, $sql);
$scientists = mysqli_fetch_assoc($res_data);

$sql = "SELECT * FROM `$table` WHERE steamid NOT IN (76561198394504608) ORDER BY `playtime` DESC LIMIT 1";
$res_data = mysqli_query($connection, $sql);
$playtime = mysqli_fetch_assoc($res_data);

$sql = "SELECT *, SUM(c4thrown + satchelsthrown * 0.2 + rocketsfired * 0.5 + rocket_hv * 0.1 + rocket_fire * 0.1 + ammo_explosive * 0.01) AS total_score 
FROM `$table` 
GROUP BY id 
ORDER BY total_score DESC 
LIMIT 1";
$res_data = mysqli_query($connection, $sql);
$reider = mysqli_fetch_assoc($res_data);

$sql = "SELECT *, SUM(chickens + boars + deers + horses + wolves + bears) AS total_score 
FROM `$table` 
GROUP BY id 
ORDER BY total_score DESC 
LIMIT 1";
$res_data = mysqli_query($connection, $sql);
$hunter = mysqli_fetch_assoc($res_data);

$sql = "SELECT *, SUM(cloth + pumpkin + corn + green_berry + blue_berry + yellow_berry + red_berry + white_berry + potato) AS total_score 
FROM `$table` 
GROUP BY id 
ORDER BY total_score DESC 
LIMIT 1";
$res_data = mysqli_query($connection, $sql);
$fermer = mysqli_fetch_assoc($res_data);

$sql = "SELECT *, SUM(wood * 0.2 + stones * 0.3 + metal_ore * 0.5 + sulfur_ore) AS total_score 
FROM `$table` 
GROUP BY id 
ORDER BY total_score DESC 
LIMIT 1";
$res_data = mysqli_query($connection, $sql);
$farmer = mysqli_fetch_assoc($res_data);

$sql = "SELECT *, SUM(anchovy * 10 + catfish * 32 + herring * 10 + orangeroughy * 37 + salmon * 22 + sardine * 10 + smallshark * 45 + troutsmall * 15 + yellowperch * 25) AS total_score 
FROM `$table` 
GROUP BY id 
ORDER BY total_score DESC 
LIMIT 1";
$res_data = mysqli_query($connection, $sql);
$fishing = mysqli_fetch_assoc($res_data);

$reiderUser = \common\models\user\User::findBySteamId($reider['steamid'], false);
$hunterUser = \common\models\user\User::findBySteamId($hunter['steamid'], false);
$fermerUser = \common\models\user\User::findBySteamId($fermer['steamid'], false);
$farmerUser = \common\models\user\User::findBySteamId($farmer['steamid'], false);
$fishingUser = \common\models\user\User::findBySteamId($fishing['steamid'], false);
$playtimeUser = \common\models\user\User::findBySteamId($playtime['steamid'], false);
$scientistsUser = \common\models\user\User::findBySteamId($scientists['steamid'], false);
$killerUser = \common\models\user\User::findBySteamId($killer['steamid'], false);
$deathsUser = \common\models\user\User::findBySteamId($deaths['steamid'], false);

?>

<div class="top_table">
    <?php if (!empty($reider)): ?>
        <?php if ($server != 'pve'): ?>
        <div class="top_table_item">
            <div class="top_table_item_image">
                <img src="<?=$reiderUser->getAvatar()?>" alt="<?=$reiderUser->username?>"/>
            </div>
            <div class="top_table_item_wrap">
                <div class="top_table_item_header">
                    <div class="top_table_item_header_name">
                        <?=Yii::t('common', 'РЕЙДЕР')?>
                    </div>
                    <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                        +500 RUB
                    </div>
                </div>
                <div class="top_table_item_body">
                    <div class="top_table_item_body_link">
                        <a target="#" href="/stats/player?steamId=<?=$reider['steamid']?>&server=<?=$server?>"><?=$reiderUser->username?></a>
                    </div>
                    <div class="top_table_item_body_score">
                        <?=round($reider['total_score'])?>
                    </div>
                </div>
            </div>
        </div>
        <div class="top_table_item">
            <div class="top_table_item_image">
                <img src="<?=$killerUser->getAvatar()?>" alt="<?=$killerUser->username?>"/>
            </div>
            <div class="top_table_item_wrap">
                <div class="top_table_item_header">
                    <div class="top_table_item_header_name">
                        <?=Yii::t('common', 'КИЛЛЕР')?>
                    </div>
                    <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                        +500 RUB
                    </div>
                </div>
                <div class="top_table_item_body">
                    <div class="top_table_item_body_link">
                        <a target="#" href="/stats/player?steamId=<?=$killer['steamid']?>&server=<?=$server?>"><?=$killerUser->username?></a>
                    </div>
                    <div class="top_table_item_body_score">
                        <?=$killer['kills']?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($scientists)): ?>
    <div class="top_table_item">
        <div class="top_table_item_image">
            <img src="<?=$scientistsUser->getAvatar()?>" alt="<?=$scientistsUser->username?>"/>
        </div>
        <div class="top_table_item_wrap">
            <div class="top_table_item_header">
                <div class="top_table_item_header_name">
                    <?=Yii::t('common', 'МИРНЫЙ')?>
                </div>
                <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                    +500 RUB
                </div>
            </div>
            <div class="top_table_item_body">
                <div class="top_table_item_body_link">
                    <a class="steam-profile" target="#" href="/stats/player?steamId=<?=$scientists['steamid']?>&server=<?=$server?>"><?=$scientistsUser->username?></a>
                </div>
                <div class="top_table_item_body_score">
                    <?=$scientists['scientists']?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($playtime)): ?>
    <div class="top_table_item">
        <div class="top_table_item_image">
            <img src="<?=$playtimeUser->getAvatar()?>" alt="<?=$playtimeUser->username?>"/>
        </div>
        <div class="top_table_item_wrap">
            <div class="top_table_item_header">
                <div class="top_table_item_header_name">
                    <?=Yii::t('common', 'МИРНЫЙ')?>
                </div>
                <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                    +500 RUB
                </div>
            </div>
            <div class="top_table_item_body">
                <div class="top_table_item_body_link">
                    <a class="steam-profile" target="#" href="/stats/player?steamId=<?=$playtime['steamid']?>&server=<?=$server?>"><?=$playtimeUser->username?></a>
                </div>
                <div class="top_table_item_body_score">
                    <?=\common\models\servers\Servers::getPlayTime($playtime['playtime'])?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($deaths)): ?>
    <div class="top_table_item">
        <div class="top_table_item_image">
            <img src="<?=$deathsUser->getAvatar()?>" alt="<?=$deathsUser->username?>"/>
        </div>
        <div class="top_table_item_wrap">
            <div class="top_table_item_header">
                <div class="top_table_item_header_name">
                    <?=Yii::t('common', 'СМЕРТЕЙ')?>
                </div>
                <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
    <!--                +500 RUB-->
                </div>
            </div>
            <div class="top_table_item_body">
                <div class="top_table_item_body_link">
                    <a class="steam-profile" target="#" href="/stats/player?steamId=<?=$deaths['steamid']?>&server=<?=$server?>"><?=$deathsUser->username?></a>
                </div>
                <div class="top_table_item_body_score">
                    <?=$deaths['deaths']?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($hunter)): ?>
    <div class="top_table_item">
        <div class="top_table_item_image">
            <img src="<?=$hunterUser->getAvatar()?>" alt="<?=$hunterUser->username?>"/>
        </div>
        <div class="top_table_item_wrap">
            <div class="top_table_item_header">
                <div class="top_table_item_header_name">
                    <?=Yii::t('common', 'ОХОТНИК')?>
                </div>
                <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                    +500 RUB
                </div>
            </div>
            <div class="top_table_item_body">
                <div class="top_table_item_body_link">
                    <a class="steam-profile" target="#" href="/stats/player?steamId=<?=$hunter['steamid']?>&server=<?=$server?>"><?=$hunterUser->username?></a>
                </div>
                <div class="top_table_item_body_score">
                    <?=$hunter['total_score']?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($fermer)): ?>
    <div class="top_table_item">
        <div class="top_table_item_image">
            <img src="<?=$fermerUser->getAvatar()?>" alt="<?=$fermerUser->username?>"/>
        </div>
        <div class="top_table_item_wrap">
            <div class="top_table_item_header">
                <div class="top_table_item_header_name">
                    <?=Yii::t('common', 'ФЕРМЕР')?>
                </div>
                <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                    +500 RUB
                </div>
            </div>
            <div class="top_table_item_body">
                <div class="top_table_item_body_link">
                    <a class="steam-profile" target="#" href="/stats/player?steamId=<?=$fermer['steamid']?>&server=<?=$server?>"><?=$fermerUser->username?></a>
                </div>
                <div class="top_table_item_body_score">
                    <?=$fermer['total_score']?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($farmer)): ?>
    <div class="top_table_item">
        <div class="top_table_item_image">
            <img src="<?=$farmerUser->getAvatar()?>" alt="<?=$farmerUser->username?>"/>
        </div>
        <div class="top_table_item_wrap">
            <div class="top_table_item_header">
                <div class="top_table_item_header_name">
                    <?=Yii::t('common', 'ФАРМЕР')?>
                </div>
                <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                    +500 RUB
                </div>
            </div>
            <div class="top_table_item_body">
                <div class="top_table_item_body_link">
                    <a class="steam-profile" target="#" href="/stats/player?steamId=<?=$farmer['steamid']?>&server=<?=$server?>"><?=$farmerUser->username?></a>
                </div>
                <div class="top_table_item_body_score">
                    <?=$farmer['total_score']?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($fishing)): ?>
    <div class="top_table_item">
        <div class="top_table_item_image">
            <img src="<?=$fishingUser->getAvatar()?>" alt="<?=$fishingUser->username?>"/>
        </div>
        <div class="top_table_item_wrap">
            <div class="top_table_item_header">
                <div class="top_table_item_header_name">
                    <?=Yii::t('common', 'РЫБАК')?>
                </div>
                <div class="top_table_item_header_bonus" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">
                    +500 RUB
                </div>
            </div>
            <div class="top_table_item_body">
                <div class="top_table_item_body_link">
                    <a class="steam-profile" target="#" href="/stats/player?steamId=<?=$fishing['steamid']?>&server=<?=$server?>"><?=$fishingUser->username?></a>
                </div>
                <div class="top_table_item_body_score">
                    <?=$fishing['total_score']?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>