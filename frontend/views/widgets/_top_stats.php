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

$sql = "SELECT *, SUM(c4thrown + satchelsthrown * 0.2 + rocketsfired * 0.5) AS total_score 
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

?>

<table class="widget_top_table">
    <thead>
    <tr>
        <th><?=Yii::t('common', 'КАТЕГОРИЯ')?></th>
        <th><?=Yii::t('common', 'НИК')?></th>
        <th><?=Yii::t('common', 'ОЧКИ')?></th>
    </tr>
    </thead>
    <tbody>
    <?php if (!empty($reider)): ?>
        <?php if ($server != 'pve'): ?>
            <!-- ТОП РЕЙДЕР -->
            <tr>
                <td class="widget_top_table_name"><?=Yii::t('common', 'РЕЙДЕР')?></td>
                <td><a class="steam-profile" target="#" href="/stats/player?steamId=<?=$reider['steamid']?>&server=<?=$server?>"><?=$reider['name']?></a></td>
                <td><?=round($reider['total_score'])?></td>
            </tr>
            <!-- ТОП КИЛЛЕР -->
            <tr>
                <td class="widget_top_table_name">
                    <?=Yii::t('common', 'КИЛЛЕР')?>
                    <?php if (!in_array($server, ['pve'])): ?>
                        <span class="widget_top_table_name_bage" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">+500 RUB</span>
                    <?php endif; ?>
                </td>
                <td><a class="steam-profile" target="#" href="/stats/player?steamId=<?=$killer['steamid']?>&server=<?=$server?>"><?=$killer['name']?></a></td>
                <td><?=$killer['kills']?></td>
            </tr>
        <?php endif; ?>
    <?php endif; ?>
    <!-- ТОП МИРНЫЙ -->
    <?php if (!empty($scientists)): ?>
        <tr>
            <td class="widget_top_table_name">
                <?=Yii::t('common', 'МИРНЫЙ')?>
                <?php if (!in_array($server, ['pve'])): ?>
                    <span class="widget_top_table_name_bage" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">+500 RUB</span>
                <?php endif; ?>
            </td>
            <td><a class="steam-profile" target="#" href="/stats/player?steamId=<?=$scientists['steamid']?>&server=<?=$server?>"><?=$scientists['name']?></a></td>
            <td><?=$scientists['scientists']?></td>
        </tr>
    <?php endif; ?>
    <!-- ТОП ОНЛАЙН -->
    <?php if (!empty($playtime)): ?>
        <tr>
            <td class="widget_top_table_name">
                <?=Yii::t('common', 'ОНЛАЙН')?>
            </td>
            <td><a class="steam-profile" target="#" href="/stats/player?steamId=<?=$playtime['steamid']?>&server=<?=$server?>"><?=$playtime['name']?></a></td>
            <td><?=\common\models\servers\Servers::getPlayTime($playtime['playtime'])?></td>
        </tr>
    <?php endif; ?>
    <!-- ТОП СМЕРТЕЙ -->
    <?php if (!empty($deaths)): ?>
        <tr>
            <td class="widget_top_table_name">
                <?=Yii::t('common', 'СМЕРТЕЙ')?>
            </td>
            <td><a class="steam-profile" target="#" href="/stats/player?steamId=<?=$deaths['steamid']?>&server=<?=$server?>"><?=$deaths['name']?></a></td>
            <td><?=$deaths['deaths']?></td>
        </tr>
    <?php endif; ?>
    <!-- ТОП ОХОТНИК -->
    <?php if (!empty($hunter)): ?>
        <tr>
            <td class="widget_top_table_name">
                <?=Yii::t('common', 'ОХОТНИК')?>
                <?php if (!in_array($server, ['pve'])): ?>
                    <span class="widget_top_table_name_bage" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">+500 RUB</span>
                <?php endif; ?>
            </td>
            <td><a class="steam-profile" target="#" href="/stats/player?steamId=<?=$hunter['steamid']?>&server=<?=$server?>"><?=$hunter['name']?></a></td>
            <td><?=$hunter['total_score']?></td>
        </tr>
    <?php endif; ?>
    <!-- Фермер -->
    <?php if (!empty($fermer)): ?>
        <tr>
            <td class="widget_top_table_name">
                <?=Yii::t('common', 'ФЕРМЕР')?>
                <?php if (!in_array($server, ['pve'])): ?>
                    <span class="widget_top_table_name_bage" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">+500 RUB</span>
                <?php endif; ?>
            </td>
            <td><a class="steam-profile" target="#" href="/stats/player?steamId=<?=$fermer['steamid']?>&server=<?=$server?>"><?=$fermer['name']?></a></td>
            <td><?=number_format($fermer['total_score'])?></td>
        </tr>
    <?php endif; ?>
    <!-- Фармер -->
    <?php if (!empty($farmer)): ?>
        <tr>
            <td class="widget_top_table_name">
                <?=Yii::t('common', 'ФАРМЕР')?>
                <?php if (!in_array($server, ['pve'])): ?>
                    <span class="widget_top_table_name_bage" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">+500 RUB</span>
                <?php endif; ?>
            </td>
            <td><a class="steam-profile" target="#" href="/stats/player?steamId=<?=$farmer['steamid']?>&server=<?=$server?>"><?=$farmer['name']?></a></td>
            <td><?=number_format($farmer['total_score'])?></td>
        </tr>
    <?php endif; ?>
    <!-- Рыбак -->
    <?php if (!empty($fishing)): ?>
        <tr>
            <td class="widget_top_table_name">
                <?=Yii::t('common', 'РЫБАК')?>
                <?php if (!in_array($server, ['pve'])): ?>
                    <span class="widget_top_table_name_bage" title="<?=Yii::t('common', 'Вознаграждение по окончанию вайпа')?>">+500 RUB</span>
                <?php endif; ?>
            </td>
            <td><a class="steam-profile" target="#" href="/stats/player?steamId=<?=$fishing['steamid']?>&server=<?=$server?>"><?=$fishing['name']?></a></td>
            <td><?=number_format($fishing['total_score'])?></td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>