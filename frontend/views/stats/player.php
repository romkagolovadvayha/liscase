<?php

/** @var yii\web\View $this */
/** @var Servers $server */
/** @var string $steamId */

use common\models\servers\Servers;
use common\models\stats\Wipe;
use yii\web\NotFoundHttpException;
use common\models\stats\Teams;

$stats = Wipe::getStats($server, $steamId);

if (empty($stats['player'])) {
    throw new NotFoundHttpException(Yii::t('common', 'Пользователь не найден или статистика еще не подгрузилась!'));
}
$player = $stats['player'];

$this->title = $player['name'] . " " . Yii::t('common', 'статистика на сервере') . " " . Yii::t('database', $server->name);


$avatar = Wipe::getAvatar($player['steamid']);
$teams = Teams::getTeams($server, $player, $stats['models']);

$lastVisit = $player['last_visit'];
if (!empty($lastVisit)) {
    $date                = new \DateTime();
    $datePlayerConnected = new \DateTime(date('Y-m-d', strtotime($lastVisit)));
    $diff                = $date->diff($datePlayerConnected);
    if ($diff->d === 0 || $player['status']) {
        $lastVisit = Yii::t('common', 'Сегодня');
    } elseif ($diff->d === 1) {
        $lastVisit = Yii::t('common', 'Вчера');
    } elseif ($diff->d === 2) {
        $lastVisit = Yii::t('common', 'Позавчера');
    } elseif ($diff->d === 3) {
        $lastVisit = Yii::t('common', '3 дня назад');
    } elseif ($diff->d === 4) {
        $lastVisit = Yii::t('common', '4 дня назад');
    } elseif ($diff->d === 5) {
        $lastVisit = Yii::t('common', '5 дней назад');
    }
}
$clan = Teams::getAllInTeams($server, $player['steamid'], $stats['models']);

$kdr      = $player['deaths'] > 0 ? round($player['kills'] / $player['deaths'], 2) : $player['kills'];
$hits     = $player['head_hits'] + $player['torso_hits'] + $player['leftarm_hits'] + $player['rightarm_hits']
    + $player['leftleg_hits'] + $player['rightleg_hits'] + $player['leftfoot_hits'] + $player['rightfoot_hits'];
$accuracy = $player['bfired'] < 1 ? "100" : (round(($hits / $player['bfired']) * 100));
if ($accuracy > 100) {
    $accuracy = 100;
}
$items = [
    [
        'name'  => Yii::t('common', 'Убийств'),
        'icon'  => 'fa-solid fa-gun',
        'count' => $player['kills'],
        'desc'  => $player['kills'],
    ],
    [
        'name'  => Yii::t('common', 'Смертей'),
        'icon'  => 'fa-solid fa-cross',
        'count' => $player['deaths'],
        'desc'  => $player['deaths'],
    ],
    [
        'name'  => 'K/D',
        'icon'  => 'fa-solid fa-crosshairs',
        'count' => $kdr,
        'desc'  => $kdr,
    ],
    [
        'name'  => Yii::t('common', 'Точность'),
        'icon'  => 'fa-solid fa-bullseye',
        'count' => $accuracy,
        'desc'  => $accuracy . '%',
    ],
];

?>

<div class="container-fluid mb-5">
    <div class="stats_player_buttons">
        <a href="/stats?server=<?=$server->tag?>" class="stats_player_buttons_back">
            <i class="fas fa-arrow-left"></i><div class="stats_player_buttons_back_title"><?=Yii::t('common', 'Назад')?></div>
        </a>
    </div>
    <div class="stats_player">
        <div class="stats_player_profile_wrap">
            <div class="stats_player_profile">
                <div class="stats_player_profile_avatar"><img src="<?=$avatar?>" alt="<?=Yii::t('common', 'Фото игрока')?> <?=$player['name']?>" width="150px"/></div>
                <div class="stats_player_profile_body">
                    <div class="stats_player_profile_body_name"><?=$player['name']?></div>
                    <div class="stats_player_profile_body_item"><?=Yii::t('common', 'Онлайн за вайп')?>: <span style="color: #aaf16e;"><?=Servers::getPlayTime($player['playtime'])?></span></div>
                    <div class="stats_player_profile_body_item">
                        <?=Yii::t('common', 'Статус')?>:
                        <span class="<?=$player['status'] ? 'online' : 'offline'?>">
                            <?=$player['status'] ? Yii::t('common', 'Онлайн') : Yii::t('common', 'Офлайн') ?>
                        </span>
                        <?php if (!$player['status'] && !empty($lastVisit)): ?>
                            <span style="font-size: 10px;vertical-align: top;font-weight: 400">(Был <?=$lastVisit?>)</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($clan)): ?>
                    <div class="stats_player_profile_body_item">
                        <?=Yii::t('common', 'Количество человек в клане')?>: <span style="color: #aaf16e;"><?=count($clan)?></span>
                    </div>
                    <?php endif; ?>
                    <div class="stats_player_profile_body_blocks">
                        <?php foreach ($items as $item): ?>
                            <div class="stats_player_profile_body_blocks_item_wrap">
                                <div class="stats_player_profile_body_blocks_item">
                                    <div class="stats_player_profile_body_blocks_item_header"><i class="<?=$item['icon']?> profile-icon"></i><?= $item['name'] ?></div>
                                    <div class="stats_player_profile_body_blocks_item_result"><?= $item['desc'] ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?=$this->render('_player_stats_killer', [
            'data' => $stats['playtime'],
            'player' => $player,
            'server' => $server,
        ]);?>
        <?=$this->render('_player_kills', [
            'player' => $player,
            'server' => $server,
            'models' => $stats['models'],
            'title' => Yii::t('common', 'История действий'),
        ]);?>
        <?=$this->render('_player_hits', [
            'player' => $player
        ]);?>
        <?=$this->render('_player_teams', [
            'player' => $player,
            'server' => $server,
            'teams' => $teams,
            'models' => $stats['models'],
            'title' => Yii::t('common', 'История команды'),
        ]);?>
        <?=$this->render('_player_stats_npc', [
            'data' => $stats['playtime'],
            'player' => $player,
            'server' => $server,
        ]);?>
        <?=$this->render('_player_stats_reider', [
            'data' => $stats['playtime'],
            'player' => $player,
            'server' => $server,
        ]);?>
        <?=$this->render('_player_stats_ferm', [
            'data' => $stats['playtime'],
            'player' => $player,
            'server' => $server,
        ]);?>
        <?=$this->render('_player_clan', [
            'player' => $player,
            'server' => $server,
            'clan' => $clan,
            'models' => $stats['models'],
            'title' => Yii::t('common', 'Участник клана'),
        ]);?>
        <?=$this->render('_player_stats_fishing', [
            'data' => $stats['playtime'],
            'player' => $player,
            'server' => $server,
        ]);?>
        <?=$this->render('_player_stats_farm', [
            'data' => $stats['playtime'],
            'player' => $player,
            'server' => $server,
        ]);?>
        <?=$this->render('_player_item', [
            'data' => $stats['kills'],
            'player' => $player,
            'server' => $server,
            'title' => Yii::t('common', 'Лучший Киллер'),
        ]);?>
        <?=$this->render('_player_item', [
            'data' => $stats['playtime'],
            'player' => $player,
            'server' => $server,
            'title' => Yii::t('common', 'ТОП Онлайн'),
        ]);?>
        <?=$this->render('_player_item', [
                'data' => $stats['reider'],
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Рейдер'),
        ]);?>
        <?=$this->render('_player_item', [
                'data' => $stats['farmer'],
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Фармер'),
        ]);?>
        <?=$this->render('_player_item', [
                'data' => $stats['fermer'],
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Фермер'),
        ]);?>
        <?=$this->render('_player_item', [
                'data' => $stats['fishing'],
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Рыбак'),
        ]);?>
        <?=$this->render('_player_item', [
                'data' => $stats['hunter'],
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Охотник'),
        ]);?>
        <?=$this->render('_player_item', [
                'data' => $stats['scientists'],
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Мирный'),
        ]);?>
        <?php /*echo $this->render('_player_item', [
                'data' => $stats['deaths'],
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'ТОП Смертей'),
        ]);*/?>
    </div>
</div>