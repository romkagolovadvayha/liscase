<?php

use common\models\servers\Servers;
use common\models\statistics\Kills;

/** @var Servers[] $servers */
/** @var array $PROJECT_STATS */
/** @var array $userData */

$kills = [];
$animals = Kills::getAnimalsList();
$animals2 = Kills::getAnimals2List();
foreach ($servers as $server) {
    $models = Kills::getKills($server);
    $kills[$server->id] = [];
    foreach ($models as $model) {
        if (empty($model['dead_name'])) {
            $model['deadLink'] = "<span class=\"stats_player_kills_item_name\">".Yii::t('common', 'Не известный')."</span>";
        } else {
            $model['deadLink'] = "<a title=\"" . Yii::t('common', 'Открыть статистику игрока') . "\" class=\"p3 link font-medium\" href=\"/stats/player?steamId={$model['dead']}&server={$server->tag}\">
                    {$model['dead_name']}
                </a>";
        }
        if (empty($model['name'])) {
            $model['link'] = "<span class=\"stats_player_kills_item_name\">".Yii::t('common', 'Не известный')."</span>";
        } else {
            $model['link'] = "<a title=\"" . Yii::t('common', 'Открыть статистику игрока') . "\" class=\"p3 link font-medium\" href=\"/stats/player?steamId={$model['steam_id']}&server={$server->tag}\">
                    {$model['name']}
                </a>";
        }
        if (!empty($animals[$model['dead']])) {
            $model['animal'] = $animals[$model['dead']];
        }
        if (!empty($animals2[$model['dead']])) {
            $model['animal2'] = $animals2[$model['dead']];
        }
        if (empty($model['weapon_name'])) {
            $model['weapon_name'] = $model['weapon'];
        }
        $kills[$server->id][] = $model;
    }
    unset($models);
}
?>

<?=Yii::$app->view->render('live.twig', [
    'SERVERS' => $servers,
    'KILLS' => $kills,
    'PROJECT_STATS' => $PROJECT_STATS,
    'USER' => $userData,
]);?>