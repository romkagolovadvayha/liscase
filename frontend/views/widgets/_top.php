<?php

use common\models\servers\Servers;
use common\models\user\UserTop;

/** @var Servers[] $servers */
/** @var array $PROJECT_STATS */
/** @var array $userData */
/** @var $SETTINGS */

$top = UserTop::getUserTop($servers);
?>

<?=Yii::$app->view->render('top.twig', [
    'SERVERS' => $servers,
    'PROJECT_STATS' => $PROJECT_STATS,
    'USER' => $userData,
    'TOP' => $top,
    'SETTINGS' => $SETTINGS
]);?>
