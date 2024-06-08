<?php

/** @var yii\web\View $this */
/** @var Servers $server */

use yii\bootstrap5\Html;
use kartik\select2\Select2;
use yii\web\View;
use yii\web\JsExpression;
use common\models\servers\Servers;

$this->title = Yii::t('common', 'Статистика сервера') . ' ' . Yii::t('database', $server->name);
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

$stats = \common\models\stats\Wipe::getStats($server, $steam_id);
$player = null;
if (!empty($stats['player'])) {
    $player = $stats['player'];
}

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
    ->andWhere('db_host IS NOT NULL')
    ->all();
?>

<div class="last_drops_wrapper">
    <?php if ($this->beginCache('_last_drops' . Yii::$app->language, ['duration' => 10])): ?>
        <?= $this->render('@frontend/views/widgets/_last_drops'); ?>
        <?php $this->endCache(); ?>
    <?php endif; ?>
</div>

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
                            'templateResult' => new JsExpression('formatRepo'),
                            'templateSelection' => new JsExpression('formatRepoSelection')
                        ],
                        'pluginEvents' => [
                            "select2:select" => "function(e) { 
                                window.location.href = '/stats/player?steamId=' + e.params.data.steam_id + '&server=' + e.params.data.server;
                            }",
                        ],
                    ]);?>
                </div>
                <div class="page_stats_tops">
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
                </div>
            </div>
        </main>
    </div>
</div>
