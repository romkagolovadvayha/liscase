<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $images */
/** @var array $names */
/** @var array $player */
/** @var string $steamId */
/** @var \common\models\user\User $user */

$kdr = Statistics::getParam($player, 'deaths') > 0 ? round(Statistics::getParam($player, 'kills') / Statistics::getParam($player, 'deaths'), 2) : Statistics::getParam($player, 'kills');

?>
<div class="page-stats__actions-in-game">
    <div class="page-stats__block py-28">
        <p class="p1 text-text-secondary mb-8"><?=Yii::t('common', 'Убийств')?></p>
        <h3 class="text-text-main"><?=number_format(Statistics::getParam($player, 'kills'), 0)?></h3>
    </div>

    <div class="page-stats__block py-28">
        <p class="p1 text-text-secondary mb-8"><?=Yii::t('common', 'Смертей')?></p>
        <h3 class="text-text-main"><?=number_format(Statistics::getParam($player, 'deaths'), 0)?></h3>
    </div>

    <div class="page-stats__block py-28">
        <p class="p1 text-text-secondary mb-8">K/D</p>
        <h3 class="text-text-main"><?=number_format($kdr, 2)?></h3>
    </div>

    <div class="page-stats__block py-28">
        <p class="p1 text-text-secondary mb-8"><?=Yii::t('common', 'Нокнули')?></p>
        <h3 class="text-text-main"><?=number_format(Statistics::getParam($player, 'wounded'), 0)?></h3>
    </div>

    <div class="page-stats__block py-28">
        <p class="p1 text-text-secondary mb-8"><?=Yii::t('common', 'Убито ботов')?></p>
        <h3 class="text-text-main"><?=number_format(Statistics::getParam($player, 'scientists'), 0)?></h3>
    </div>

    <div class="page-stats__block py-28">
        <p class="p1 text-text-secondary mb-8"><?=Yii::t('common', 'Зарейдено шкафов')?></p>
        <h3 class="text-text-main"><?=number_format(Statistics::getParam($player, 'tcsdestroyed'), 0)?></h3>
    </div>
</div>