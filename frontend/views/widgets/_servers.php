<?php

use common\models\servers\Servers;

/** @var Servers[] $servers */
/** @var array $PROJECT_STATS */
/** @var $SETTINGS */

$lang = substr(Yii::$app->language, 0, 2);
$this->registerJs(
    <<<JS
        var timers = $('.server_timer');
        for (var i = 0; i < timers.length; i++) {
            var dateTime = $(timers[i]).attr('data-time');
            var left = moment.unix(dateTime);
            $(timers[i]).html(left.locale('{$lang}').fromNow());
        }
JS
);


?>

<?=Yii::$app->view->render('servers.twig', [
    'SERVERS' => $servers,
    'PROJECT_STATS' => $PROJECT_STATS,
    'SETTINGS' => $SETTINGS
]);?>