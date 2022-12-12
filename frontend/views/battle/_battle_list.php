<?php

use yii\web\View;
use common\models\battle\Battle;

/** @var View $this */
/** @var string $status */

/** @var Battle[] $battles */
$battles = Battle::find()
    ->andWhere(['status' => $status])
    ->all();
?>
<div class="battle_rows">
    <?php if (empty($battles)): ?>
        <p><?=Yii::t('common', 'Сражений еще не было!')?></p>
    <?php endif; ?>
    <?php foreach ($battles as $battle): ?>
        <a href="/battle/game?id=<?=$battle->id?>" class="battle_rows_item">
            <div class="battle_rows_item_players">
                <div class="battle_rows_item_players_player1" style="background-image: url(<?=$battle->player1->userProfile->avatar?>)"></div>
                <div class="battle_rows_item_players_separator">VS</div>
                <div class="battle_rows_item_players_player2" style="background-image: url(<?=!empty($battle->player2) ? $battle->player2->userProfile->avatar : ''?>)">
                    <?php if (empty($battle->player2)): ?>
                        <div class="battle_rows_item_players_anonim">?</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="battle_rows_item_rate">
                <?php if (!empty($battle->player1Rate)): ?>
                <div class="battle_rows_item_rate_rate1">
                    <div class="battle_rows_item_rate_rate1_image" style="background-image: url(<?=$battle->player1Rate->userDrop->drop[0]->imageOrig->getImagePubUrl()?>)"></div>
                    <div class="battle_rows_item_rate_rate1_info">
                        <div class="battle_rows_item_rate_rate1_info_title">
                            <?=Yii::t('database', $battle->player1Rate->userDrop->drop[0]->name)?>
                        </div>
                        <div class="battle_rows_item_rate_rate1_info_price">
                            <?=$battle->player1Rate->userDrop->drop[0]->price?>
                            <span class="currency"><?=$battle->player1Rate->userDrop->drop[0]->currency?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="battle_rows_item_status">
                <?=\yii\helpers\ArrayHelper::getValue(Battle::getStatusList(), $battle->status)?>
            </div>
        </a>
    <?php endforeach; ?>
</div>

