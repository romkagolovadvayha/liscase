<?php

use common\models\servers\Servers;
use common\models\user\UserTop;

/** @var Servers[] $servers */
/** @var array $PROJECT_STATS */
/** @var array $userData */
/** @var $SETTINGS */

$top = [];
$serversList = [];
foreach ($servers as $server) {
    if ($userData['SERVER_ACTIVE_ID'] != $server->id) {
        continue;
    }
    $serversList[] = $server;
    $top[$server->id] = UserTop::getUserTops($server, $server->currentWipe());
}
foreach ($servers as $i => $server) {
    if ($userData['SERVER_ACTIVE_ID'] == $server->id) {
        continue;
    }
    if ($i > 3) {
        break;
    }
    $serversList[] = $server;
    $top[$server->id] = UserTop::getUserTops($server, $server->currentWipe());
}

$goldAmount = Yii::$app->settings->get('tops_gold_amount');
$silverAmount = Yii::$app->settings->get('tops_silver_amount');
$silverAmount = Yii::$app->settings->get('tops_silver_amount');
$bronzeAmount = Yii::$app->settings->get('tops_bronze_amount');
?>

<?=Yii::$app->view->render('top.twig', [
    'SERVERS' => $serversList,
    'PROJECT_STATS' => $PROJECT_STATS,
    'USER' => $userData,
    'TOP' => $top,
    'SETTINGS' => $SETTINGS,
    'AMOUNT_GOLD' => $goldAmount,
    'AMOUNT_SILVER' => $silverAmount,
    'AMOUNT_BRONZE' => $bronzeAmount,
]);?>
