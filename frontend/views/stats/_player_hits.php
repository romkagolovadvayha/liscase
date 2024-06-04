<?php

use common\models\servers\Servers;

/** @var array $player */
/** @var Servers $server */

?>

<div class="stats_player_hits_wrap">
    <div class="stats_player_hits">
            <div class="stats_player_hits_hover"><i class="fa-solid fa-circle-info"></i> <?=Yii::t('common', 'Информация о стрельбе')?></div>
            <img class="stats_player_hits_model" src="/images/player2.png"/>
            <div class="stats_player_hits_container">
                <div class='hitcount head'>
                    <div class='hitcount-part'><?=Yii::t('common', 'Голова')?></div>
                    <?php echo "<p class='hitcount-result'>" . $player['head_hits'] . "</p>" ?>
                </div>
                <div class='hitcount torso'>
                    <div class='hitcount-part'><?=Yii::t('common', 'Тело')?></div>
                    <?php echo "<p class='hitcount-result'>" . $player['torso_hits'] . "</p>" ?>
                </div>
                <div class='hitcount leftarm'>
                    <div class='hitcount-part'><?=Yii::t('common', 'Левая рука')?></div>
                    <?php echo "<p class='hitcount-result'>" . $player['leftleg_hits'] . "</p>" ?>
                </div>
                <div class='hitcount rightarm'>
                    <div class='hitcount-part'><?=Yii::t('common', 'Правая рука')?></div>
                    <?php echo "<p class='hitcount-result'>" . $player['leftleg_hits'] . "</p>" ?>
                </div>
                <div class='hitcount leftleg'>
                    <div class='hitcount-part'><?=Yii::t('common', 'Левая нога')?></div>
                    <?php echo "<p class='hitcount-result'>" . $player['leftleg_hits'] . "</p>" ?>
                </div>
                <div class='hitcount rightleg'>
                    <div class='hitcount-part'><?=Yii::t('common', 'Правая нога')?></div>
                    <?php echo "<p class='hitcount-result'>" . $player['rightleg_hits'] . "</p>" ?>
                </div>
                <div class='hitcount leftfoot'>
                    <div class='hitcount-part'><?=Yii::t('common', 'Левая пятка')?></div>
                    <?php echo "<p class='hitcount-result'>" . $player['leftfoot_hits'] . "</p>" ?>
                </div>
                <div class='hitcount rightfoot'>
                    <div class="hitcount-part"><?=Yii::t('common', 'Правая пятка')?></div>
                    <?php echo "<p class='hitcount-result'>" . $player['rightfoot_hits'] . "</p>" ?>
                </div>
            </div>
    </div>
</div>
