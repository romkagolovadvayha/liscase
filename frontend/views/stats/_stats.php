<?php

/** @var \common\models\servers\Servers $server */
/** @var array $models */

$layout = [
    'topEnd' => null,
    'topStart' => 'search',
    'bottom' => 'paging',
    'bottomStart' => null,
    'bottomEnd' => null,
];
$layout = str_replace('"', "&quot;", json_encode($layout));
$language = [
    'search' => '<svg class="Search-module__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"></path></svg>',
    "zeroRecords" => "Ничего не найдено!",
    "paginate" => [
        "first"    => "Первая",
        "last"     => "Последняя",
        "next"     => "Следующая",
        "previous" => "Предыдущая",
    ],
];
$language = str_replace('"', "&quot;", json_encode($language));

?>

<div id="stats-table">
    <table class="table" data-scroll-x="true" data-page-length="20" data-layout="<?=$layout?>" data-language="<?=$language?>" data-order="[[ 2, &quot;desc&quot; ]]">
        <thead>
        <tr>
            <th>Имя</th>
            <th><i class="fa-solid fa-clock"></i> В игре</th>
            <th><i class="fa-solid fa-gun"></i> Убийств</th>
            <th><i class="fa-solid fa-cow"></i> Фермер</th>
            <th><i class="fa-solid fa-house-chimney-window"></i> Фармер</th>
            <th><i class="fa-solid fa-crosshairs"></i> Охотник</th>
            <th><i class="fa-solid fa-fish-fins"></i> Рыбак</th>
            <th><i class="fa-solid fa-walkie-talkie"></i> Мирный</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($models as $row): ?>
            <?php
            $kdr = $row['deaths'] > 0 ? round($row['kills'] / $row['deaths'], 2) : $row['kills'];
            $playtime = in_array($row['steamid'], [76561198394504608]) ? $row['playtime'] / 2 : $row['playtime'];
            ?>
            <tr class="leaderboard-result">
                <td data-search="<?=$row['name']?>" data-order="<?=$row['name']?>">
                    <a href="/stats/profile?server=<?=$server->tag?>&steamId=<?=$row['steamid']?>" class="link_name_name" title="Открыть подробную статистику"><?=$row['name']?></a>
                    <a href="https://steamcommunity.com/profiles/<?=$row['steamid']?>" target="_blank" class="link_name_steamId" title="Открыть профиль Steam"><?=$row['steamid']?></a>
                </td>
                <td data-order="<?=$playtime?>"><?=\common\models\servers\Servers::getPlayTime($playtime)?></td>
                <td><?=$row['kills']?></td>
                <td><?=$row['fermer']?></td>
                <td><?=$row['farmer']?></td>
                <td><?=$row['hunter']?></td>
                <td><?=$row['fishing']?></td>
                <td><?=$row['scientists']?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>