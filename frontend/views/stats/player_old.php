<?php

/** @var yii\web\View $this */
/** @var Servers $server */
/** @var string $steamId */

use common\models\servers\Servers;
use common\models\statistics\Statistics;
use yii\web\NotFoundHttpException;
use common\models\statistics\Teams;
use common\models\user\User;
use yii\bootstrap5\Html;
use kartik\select2\Select2;
use yii\web\View;
use yii\web\JsExpression;

$user = User::findBySteamId($steamId, true);

if (empty($user)) {
    throw new NotFoundHttpException(Yii::t('common', 'Пользователь не найден или статистика еще не подгрузилась!'));
}

$wipeDate = (new \DateTime($server->wipe))->format('Y-m-d') . "/" . (new \DateTime($server->next_wipe))->format('Y-m-d');

$this->title = $user->username . " " . Yii::t('common', 'статистика на сервере') . " " . Yii::t('database', $server->name);

$teams = Teams::getTeams($server, $user);
$clan = Teams::getAllInTeams($server, $user->steam_id);
$stats = \common\models\statistics\Statistics::getStats($server, $user->steam_id);
$player = null;
if (!empty($stats['player'])) {
    $player = $stats['player'];
}

/** @var Servers[] $servers */
$servers = Servers::find()
                  ->cache(30)
                  ->andWhere(['status' => Servers::STATUS_ACTIVE])
                  ->orderBy(['sort' => SORT_ASC])
                  ->all();

$formatJs = <<< 'JS'
var formatRepo = function (repo) {
    if (repo.loading) {
        return repo.text;
    }
    var markup =
'<a href="/stats/player?steamId=' + repo.steam_id + '&server=' + repo.server + '" class="">' + 
    '<div class="stats_player_search_name">' + repo.name + '</div>' +
    '<div class="stats_player_search_steam_id">' + repo.steam_id + '</div>' +
'</a>';
    return '<div style="overflow:hidden;">' + markup + '</div>';
};
var formatRepoSelection = function (repo) {
    return repo.name || repo.text;
}
JS;
$this->registerJs($formatJs, View::POS_HEAD);

$resultsJs = <<< JS
function (data, params) {
    return {
        results: data.items
    };
}
JS;
\frontend\assets\ChartistAsset::register($this);
?>
<style>
    .select2-container--krajee-bs5:not(.select2-container--disabled) .select2-dropdown {
        margin-top: -45px;
    }
</style>

<div class="mb-5">
    <div class="stats_player_buttons">
        <div class="stats_player_card_wrap">
            <div class="stats_player_card">
                <div class="stats_player_card_avatar">
                    <img src="<?=$user->getAvatar()?>" alt="<?=Yii::t('common', 'Фото игрока')?> <?=$user->username?>"/>
                </div>
                <div class="stats_player_card_body">
                    <div class="stats_player_card_body_name">
                        <span><?=$user->username?></span> <a href="https://steamcommunity.com/profiles/<?=$user->steam_id?>" class="stats_player_card_body_name_steam" target="_blank" title="<?=Yii::t('common', 'Перейти в профиль Steam')?>"><i class="fab fa-steam"></i></a>
                    </div>
                    <div class="stats_player_card_body_item">
                        <?=Yii::t('common', 'Онлайн за вайп')?>: <span style="color: #aaf16e;"><?=Servers::getPlayTime(Statistics::getParam($player, 'playtime'))?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="stats_player_search_wrap">
            <?=Select2::widget([
                                   'name' => 'stats-search',
                                   'options' => ['placeholder' => Yii::t('common', 'Введите ник или Steam ID...')],
                                   'pluginOptions' => [
                                       'allowClear' => true,
                                       'minimumInputLength' => 1,
                                       'ajax' => [
                                           'url' => "/stats/search?server={$server->tag}",
                                           'dataType' => 'json',
                                           'delay' => 250,
                                           'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                           'processResults' => new JsExpression($resultsJs),
                                           'cache' => true
                                       ],
                                       'escapeMarkup' => new JsExpression('function (markup) { return markup; }'),
                                       'templateResult' => new JsExpression('formatRepo'),
                                       'templateSelection' => new JsExpression('formatRepoSelection')
                                   ],
                                   'pluginEvents' => [
                                       "select2:select" => "function(e) { 
                                window.location.href = '/stats/player?steamId=' + e.params.data.steam_id + '&server=' + e.params.data.server;
                            }",
                                   ],
                               ]);?>
            <div class="stats_player_search_icon">
                <i class="fas fa-search"></i>
            </div>
        </div>
        <?php foreach ($servers as $item): ?>
            <a href="/stats/player?steamId=<?=$user->steam_id?>&server=<?=$item->tag?>" class="stats_player_buttons_server<?=$item->tag === $server->tag ? ' page_stats_servers_item_active' : ''?>">
                <?=Yii::t('database', $item->name)?>
            </a>
        <?php endforeach; ?>
    </div>
    <?=$this->render('_player_stats_npc', [
        'data' => $stats['playtime'],
        'player' => $player,
        'server' => $server,
        'wipeDate' => $wipeDate,
        'steamId' => $steamId,
    ]);?>
    <?=$this->render('_player_stats_farm', [
        'data' => $stats['playtime'],
        'player' => $player,
        'server' => $server,
    ]);?>
    <div class="stats_player_stats_wrap_wrap">
        <?=$this->render('_player_stats_ferm', [
            'data' => $stats['playtime'],
            'player' => $player,
            'server' => $server,
        ]);?>
        <?=$this->render('_player_stats_fishing', [
            'data' => $stats['playtime'],
            'player' => $player,
            'server' => $server,
        ]);?>
    </div>
    <div class="stats_player_teams_wrap_wrap">
        <?=$this->render('_player_teams', [
            'player' => $player,
            'server' => $server,
            'teams' => $teams,
            'steam_id' => $steamId,
            'title' => Yii::t('common', 'История команды'),
        ]);?>
        <?=$this->render('_player_clan', [
            'player' => $player,
            'server' => $server,
            'clan' => $clan,
            'steam_id' => $steamId,
            'title' => Yii::t('common', 'Команда'),
        ]);?>
    </div>
    <div class="stats_player_stats_wrap_wrap">
        <?=$this->render('_player_stats_food', [
            'data' => $stats['playtime'],
            'player' => $player,
            'server' => $server,
        ]);?>
        <?=$this->render('_player_stats_tea', [
            'data' => $stats['playtime'],
            'player' => $player,
            'server' => $server,
        ]);?>
        <?=$this->render('_player_stats_level_card', [
            'data' => $stats['playtime'],
            'player' => $player,
            'server' => $server,
        ]);?>
        <?=$this->render('_player_stats_medical', [
            'data' => $stats['playtime'],
            'player' => $player,
            'server' => $server,
        ]);?>
        <?=$this->render('_player_stats_hunter', [
            'data' => $stats['playtime'],
            'player' => $player,
            'server' => $server,
        ]);?>
    </div>
    <div class="stats_player">
        <?=$this->render('_player_item2', [
            'data' => $stats['kills'],
            'user' => $user,
            'player' => $player,
            'server' => $server,
            'title' => Yii::t('common', 'Лучший Киллер'),
        ]);?>
        <?=$this->render('_player_item2', [
            'data' => $stats['playtime'],
            'user' => $user,
            'player' => $player,
            'server' => $server,
            'title' => Yii::t('common', 'ТОП Онлайн'),
        ]);?>
        <?=$this->render('_player_item2', [
                'data' => $stats['reider'],
                'user' => $user,
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Рейдер'),
        ]);?>
        <?=$this->render('_player_item2', [
                'data' => $stats['farmer'],
                'user' => $user,
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Фармер'),
        ]);?>
        <?=$this->render('_player_item2', [
                'data' => $stats['fermer'],
                'user' => $user,
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Фермер'),
        ]);?>
        <?=$this->render('_player_item2', [
                'data' => $stats['fishing'],
                'user' => $user,
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Рыбак'),
        ]);?>
        <?=$this->render('_player_item2', [
                'data' => $stats['hunter'],
                'user' => $user,
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Охотник'),
        ]);?>
        <?=$this->render('_player_item2', [
                'data' => $stats['scientists'],
                'user' => $user,
                'player' => $player,
                'server' => $server,
                'title' => Yii::t('common', 'Лучший Мирный'),
        ]);?>
    </div>
</div>