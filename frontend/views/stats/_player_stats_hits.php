<?php

use common\models\servers\Servers;
use common\models\statistics\Statistics;

/** @var Servers $server */
/** @var array $images */
/** @var array $names */
/** @var array $player */
/** @var string $steamId */
/** @var \common\models\user\User $user */

?>
<section class="page-stats__gamer-stats_wrap page-stats__block w-50p" style="height: 616px">
    <h3 class="mb-32"><?=Yii::t('common', 'Статистика по попаданиям')?></h3>

    <div class="page-stats__gamer-stats gamer-stats">
        <span class="gamer-stats__item gamer-stats__head"><?=Statistics::getParam($player, 'hits_head')?></span>
        <span class="gamer-stats__item gamer-stats__left-arm"><?=Statistics::getParam($player, 'hits_lefthand')?></span>
        <span class="gamer-stats__item gamer-stats__left-hip"><?=Statistics::getParam($player, 'hits_leftleg')?></span>
        <span class="gamer-stats__item gamer-stats__left-leg"><?=Statistics::getParam($player, 'hits_leftfoot')?></span>
        <span class="gamer-stats__item gamer-stats__right-arm"><?=Statistics::getParam($player, 'hits_righthand')?></span>
        <span class="gamer-stats__item gamer-stats__right-hip"><?=Statistics::getParam($player, 'hits_rightleg')?></span>
        <span class="gamer-stats__item gamer-stats__right-leg"><?=Statistics::getParam($player, 'hits_rightfoot')?></span>

        <img src="/images/design/stats/gamer.png" alt="" />
    </div>
</section>
<!--<div class="stats_player_stats_killer_hits_hits">-->
<!--    <div class="stats_player_stats_killer_hits_hits_item stats_player_stats_killer_hits_hits_neck">--><?//=Statistics::getParam($player, 'hits_neck')?><!--</div>-->
<!--    <div class="stats_player_stats_killer_hits_hits_item stats_player_stats_killer_hits_hits_chest">--><?//=Statistics::getParam($player, 'hits_chest')?><!--</div>-->
<!--    <div class="stats_player_stats_killer_hits_hits_item stats_player_stats_killer_hits_hits_lowerspine">--><?//=Statistics::getParam($player, 'hits_lowerspine')?><!--</div>-->
<!--</div>-->