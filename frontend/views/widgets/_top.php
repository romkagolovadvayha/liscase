<?php

use common\models\servers\Servers;
use common\models\user\UserTop;

/** @var Servers[] $servers */
/** @var array $PROJECT_STATS */
/** @var array $userData */

$top = [];
foreach ($servers as $server) {
    $sql = "
    SELECT id, user_id, `key`, value, server_id, wipe
    FROM (
        SELECT id, user_id, `key`, value, server_id, wipe,
            MAX(value) OVER (PARTITION BY `key`, server_id) AS max_value
        FROM user_top
        WHERE server_id = :server_id
          AND wipe = :wipe
    ) AS ranked
    WHERE value = max_value
    ORDER BY server_id, `key`, value DESC
";

    $userTop = UserTop::findBySql($sql, [
        ':server_id' => $server->id,
        ':wipe' => $server->currentWipe(),
    ])->cache(60)
      ->all();

    $top[$server->id] = $userTop;
}
?>

<?=Yii::$app->view->render('top.twig', [
    'SERVERS' => $servers,
    'PROJECT_STATS' => $PROJECT_STATS,
    'USER' => $userData,
    'TOP' => $top,
]);?>
