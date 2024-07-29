<?php

/** @var yii\web\View $this */
/** @var Servers $server */
/** @var string $steamId */

use common\models\servers\Servers;
use common\models\stats\Wipe;
use yii\web\NotFoundHttpException;
use common\models\stats\Teams;
use common\models\user\User;

$user = User::findBySteamId($steamId, true);

if (empty($user)) {
    throw new NotFoundHttpException(Yii::t('common', 'Пользователь не найден или статистика еще не подгрузилась!'));
}

$stats = Wipe::getStats($server, $steamId);
if (!empty($stats['player'])) {
    $player = $stats['player'];
} else {
    $player = Wipe::getArray();
}

$this->title = $user->username . " " . Yii::t('common', 'статистика на сервере') . " " . Yii::t('database', $server->name);

$teams = Teams::getTeams($server, $user, $stats['models']);

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
$clan = Teams::getAllInTeams($server, $user->steam_id, $stats['models']);

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
];

/** @var Servers[] $servers */
$servers = Servers::find()
                  ->cache(30)
                  ->andWhere('db_host IS NOT NULL')
                  ->all();
?>

<div class="container-fluid mb-5">
    <div class="stats_player_buttons">
        <a href="/stats?server=<?=$server->tag?>" class="stats_player_buttons_back">
            <i class="fas fa-arrow-left"></i><div class="stats_player_buttons_back_title"><?=Yii::t('common', 'Назад')?></div>
        </a>
        <?php foreach ($servers as $item): ?>
            <a href="/stats/player?steamId=<?=$user->steam_id?>&server=<?=$item->tag?>" class="stats_player_buttons_server<?=$item->tag === $server->tag ? ' page_stats_servers_item_active' : ''?>">
                <?=Yii::t('database', $item->name)?>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="stats_player">
        <div class="stats_player_profile_wrap">
            <div class="stats_player_profile">
                <div class="stats_player_profile_avatar"><img src="<?=$user->getAvatar()?>" alt="<?=Yii::t('common', 'Фото игрока')?> <?=$user->username?>" width="150px"/></div>
                <div class="stats_player_profile_body">
                    <div class="stats_player_profile_body_name"><span><?=$user->username?></span> <a href="https://steamcommunity.com/profiles/<?=$user->steam_id?>" class="stats_player_profile_body_name_steam" target="_blank" title="<?=Yii::t('common', 'Перейти в профиль Steam')?>"><i class="fab fa-steam"></i></a></div>
                    <div class="stats_player_profile_body_item"><?=Yii::t('common', 'Онлайн за вайп')?>: <span style="color: #aaf16e;"><?=Servers::getPlayTime($player['playtime'])?></span></div>
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
            'user' => $user,
            'server' => $server,
            'models' => $stats['models'],
            'title' => Yii::t('common', 'История действий'),
        ]);?>
        <?php //$this->render('_player_hits', ['player' => $player]); ?>
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
            'user' => $user,
            'player' => $player,
            'server' => $server,
            'title' => Yii::t('common', 'Лучший Киллер'),
        ]);?>
        <?=$this->render('_player_item', [
            'data' => $stats['playtime'],
            'user' => $user,
            'player' => $player,
            'server' => $server,
            'title' => Yii::t('common', 'ТОП Онлайн'),
        ]);?>
        <?=$this->render('_player_item', [
                'data' => $stats['reider'],
                'user' => $user,
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Рейдер'),
        ]);?>
        <?=$this->render('_player_stats_barrel', [
            'data' => $stats['playtime'],
            'player' => $player,
            'server' => $server,
        ]);?>
        <?=$this->render('_player_item', [
                'data' => $stats['farmer'],
                'user' => $user,
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Фармер'),
        ]);?>
        <?=$this->render('_player_item', [
                'data' => $stats['fermer'],
                'user' => $user,
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Фермер'),
        ]);?>
        <?=$this->render('_player_item', [
                'data' => $stats['fishing'],
                'user' => $user,
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Рыбак'),
        ]);?>
        <?=$this->render('_player_item', [
                'data' => $stats['hunter'],
                'user' => $user,
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Охотник'),
        ]);?>
        <?=$this->render('_player_item', [
                'data' => $stats['scientists'],
                'user' => $user,
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