<?php

use common\models\servers\Servers;
use common\models\statistics\Kills;

/** @var Servers[] $servers */
/** @var array $PROJECT_STATS */
/** @var array $userData */
/** @var $SETTINGS */

$kills = Kills::getLive($servers);
?>

<?=Yii::$app->view->render('live.twig', [
    'SERVERS' => $servers,
    'KILLS' => $kills,
    'PROJECT_STATS' => $PROJECT_STATS,
    'USER' => $userData,
    'SETTINGS' => $SETTINGS
]);?>