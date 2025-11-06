<?php

use common\models\servers\Servers;
use common\models\statistics\Kills;

/** @var Servers[] $servers */
/** @var array $PROJECT_STATS */
/** @var array $userData */
/** @var $SETTINGS */

if ($PAGE === 'stats') {
    return;
}
$kills = Kills::getLive($servers);
$serversList = [];
foreach ($servers as $server) {
    if ($userData['SERVER_ACTIVE_ID'] != $server->id) {
        continue;
    }
    $serversList[] = $server;
}
$count = 0;
foreach ($servers as $i => $server) {
    if ($userData['SERVER_ACTIVE_ID'] == $server->id) {
        continue;
    }
    if ($count > 3) break;
    $serversList[] = $server;
    $count++;
}
?>

<?=Yii::$app->view->render('live.twig', [
    'SERVERS' => $serversList,
    'KILLS' => $kills,
    'PROJECT_STATS' => $PROJECT_STATS,
    'USER' => $userData,
    'SETTINGS' => $SETTINGS
]);?>