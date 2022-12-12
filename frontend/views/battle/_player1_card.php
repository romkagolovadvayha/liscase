<?php

use yii\web\View;
use common\models\battle\Battle;
use frontend\widgets\Alert;

/** @var View $this */
/** @var Battle $battle */

?>
<div class="battle_view_players_player1">
    <div class="battle_view_players_player1_image" style="background-image: url(<?=$battle->player1->userProfile->avatar?>)"></div>
    <div class="battle_view_players_player1_name"><?=$battle->player1->userProfile->name?></div>
    <div class="battle_view_players_player1_rate_wrapper">
        <div class="battle_view_players_player1_rate">
            <div class="battle_view_players_player1_rate_header"><?=Yii::t('common', "Ставка")?></div>
            <div class="battle_view_players_player1_rate_image" style="background-image: url(<?=$battle->player1Rate->userDrop->drop[0]->imageOrig->getImagePubUrl()?>)"></div>
            <div class="battle_view_players_player1_rate_info">
                <div class="battle_view_players_player1_rate_info_title">
                    <?=Yii::t('database', $battle->player1Rate->userDrop->drop[0]->name)?>
                </div>
            </div>
        </div>
    </div>
    <div class="battle_view_players_player1_rate_info_price">
        <?=$battle->player1Rate->userDrop->drop[0]->price?>
        <span class="currency"><?=$battle->player1Rate->userDrop->drop[0]->currency?></span>
    </div>
    <?php if ($battle->player1->id === Yii::$app->user->id && $battle->status === Battle::STATUS_WAIT_PLAYER): ?>
        <div class="battle_view_controls">
            <a href="/battle/reject?id=<?=$battle->id?>" class="btn danger"><?=Yii::t('common', "Отменить ставку")?></a>
        </div>
    <?php endif; ?>
</div>