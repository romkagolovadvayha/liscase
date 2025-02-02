<?php

/** @var yii\web\View $this */
/** @var Servers $server */

use yii\bootstrap5\Html;
use kartik\select2\Select2;
use yii\web\View;
use yii\web\JsExpression;
use common\models\servers\Servers;

$this->title = Yii::t('common', 'Статистика сервера') . ' ' . Yii::t('database', $server->name);
$this->params['meta_description'] = Yii::t('common', "Статистика игроков Rust.");
$this->params['meta_keywords'] = Yii::t('common', "стастистика игроков, статистика сервера, статистика rust");

$formatJs = <<< 'JS'
var formatRepo = function (repo) {
    if (repo.loading) {
        return repo.text;
    }
    var markup =
'<a href="/stats/player?steamId=' + repo.steam_id + '&server=' + repo.server + '" class="">' + 
    '<div class="page_stats_search_name">' + repo.name + '</div>' +
    '<div class="page_stats_search_steam_id">' + repo.steam_id + '</div>' +
'</a>';
    return '<div style="overflow:hidden;">' + markup + '</div>';
};
var formatRepoSelection = function (repo) {
    return repo.name || repo.text;
}
JS;
$this->registerJs($formatJs, View::POS_HEAD);

$steam_id = null;
if (!Yii::$app->user->isGuest) {
    $steam_id = Yii::$app->user->identity->auth->source_id;
}

$stats = \common\models\statistics\Statistics::getStats($server, $steam_id);

$player = null;
if (!empty($stats['player'])) {
    $player = $stats['player'];
}
$user = \common\models\user\User::findBySteamId($steam_id);

$resultsJs = <<< JS
function (data, params) {
    return {
        results: data.items
    };
}
JS;

/** @var Servers[] $servers */
$servers = Servers::find()
    ->cache(30)
    ->andWhere(['status' => Servers::STATUS_ACTIVE])
    ->orderBy(['sort' => SORT_ASC])
    ->all();

?>

<div class="container-fluid mb-5">
    <div class="main_wrap">
        <main id="main" role="main">
            <div class="page_stats">
                <div class="page_stats_servers_wrap">
                    <div class="page_stats_servers">
                        <?php foreach ($servers as $item): ?>
                            <a href="/stats?server=<?=$item->tag?>" class="page_stats_servers_item<?=$item->tag === $server->tag ? ' page_stats_servers_item_active' : ''?>">
                                <?=Yii::t('database', $item->name)?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="page_stats_search_wrap">
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
                            'templateResult' => new JsExpression("
                                function (repo) {
                                    if (repo.loading) {
                                        return repo.text;
                                    }
                                    var markup =
                                        '<a href=\"/stats/player?steamId=' + repo.steam_id + '&server=' + repo.server + '\" class=\"\">' +
                                        '<div class=\"page_stats_search_name\">' + repo.name + '</div>' +
                                        '<div class=\"page_stats_search_steam_id\">' + repo.steam_id + '</div>' +
                                        '</a>';
                                    return '<div style=\"overflow:hidden;\">' + markup + '</div>';
                                }
                            "),
                            'templateSelection' => new JsExpression("
                                function (repo) {
                                    return repo.name || repo.text;
                                }
                            "),
                        ],
                        'pluginEvents' => [
                            "select2:select" => "function(e) { 
                                window.location.href = '/stats/player?steamId=' + e.params.data.steam_id + '&server=' + e.params.data.server;
                            }",
                        ],
                    ]);?>
                </div>
                <div class="page_stats_tops">
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
        </main>
    </div>
</div>
