<?php

use yii\web\View;
use common\models\battle\Battle;
use common\models\user\UserDrop;
use yii\widgets\ActiveForm;

/** @var View $this */
/** @var Battle $battle */

if (!empty($battle->player_winner_user_id)) {
    $this->registerJs(
        <<<JS
    data[data.length] = { id: '2', color: '#1f7588', avatar: '{$battle->player2->userProfile->avatar}' };
JS, View::POS_BEGIN
    );
}
?>
<div class="battle_view_players_player2">
    <div class="battle_view_players_player2_image" style="background-image: url(<?=!empty($battle->player2) ? $battle->player2->userProfile->avatar : ''?>)">
        <?php if (empty($battle->player2)): ?>
            <div class="battle_view_players_player2_image_anonim">?</div>
        <?php endif; ?>
    </div>
    <div class="battle_view_players_player2_name"><?=!empty($battle->player2) ? $battle->player2->userProfile->name : Yii::t('common', "Не выбран")?></div>
    <?php if (Yii::$app->user->isGuest || !empty($battle->player2) || $battle->player1_user_id === Yii::$app->user->id): ?>
        <div class="battle_view_players_player2_rate_wrapper">
            <div class="battle_view_players_player2_rate">
                <div class="battle_view_players_player2_rate_header"><?=Yii::t('common', "Ставка")?></div>
                <div class="battle_view_players_player2_rate_image" style="background-image: url(<?=!empty($battle->player2Rate) ? $battle->player2Rate->userDrop->drop[0]->imageOrig->getImagePubUrl() : ''?>)">
                    <?php if (empty($battle->player2)): ?>
                        <div class="battle_view_players_player2_rate_image_anonim">?</div>
                    <?php endif; ?>
                </div>
                <div class="battle_view_players_player2_rate_info">
                    <div class="battle_view_players_player2_rate_info_title">
                        <?php if (!empty($battle->player2)): ?>
                            <?=Yii::t('database', $battle->player2Rate->userDrop->drop[0]->name)?>
                        <?php else: ?>
                            <?=Yii::t('common', 'Ожидание игрока')?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php if (!empty($battle->player2)): ?>
            <div class="battle_view_players_player2_rate_info_price">
                <?=$battle->player2Rate->userDrop->drop[0]->price?>
                <span class="currency"><?=$battle->player2Rate->userDrop->drop[0]->currency?></span>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <?php
        /** @var UserDrop[] $userDrops */
        $userDrops = Yii::$app->user->identity->getUserDrop()
            ->andWhere(['status' => UserDrop::STATUS_ACTIVE])
            ->all();
        ?>
        <div class="battle_view_players_player2_select_rate_wrapper">
            <div class="battle_view_players_player2_select_rate">
                <div class="battle_view_players_player2_select_rate_header"><?=Yii::t('common', "Сделайте ставку")?></div>
                <?php if (empty($userDrops)):?>
                    <div class="text-center mt-4">
                        <p>
                            <?=Yii::t('common', 'В вашем инвентаре пока нет вещей')?>
                        </p>
                        <a class="btn box_entity_card_actions_inventory_btn" href="/user/inventory">
                            <?=Yii::t('common', 'Открыть инвентарь')?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="battle_view_players_player2_select_rate_inventory">
                        <?php foreach ($userDrops as $userDrop): ?>
                            <?php foreach ($userDrop->drop as $drop): ?>
                                <div class="battle_view_players_player2_select_rate_inventory_card<?=' drop_card level' . $drop->getLevel()?>">
                                    <div class="battle_view_players_player2_select_rate_inventory_card_info">
                                        <div class="battle_view_players_player2_select_rate_inventory_card_info_title"><?=Yii::t('common', $drop->getShortName())?></div>
                                    </div>
                                    <div class="battle_view_players_player2_select_rate_inventory_card_image">
                                        <img src="<?= $drop->imageOrig->getImagePubUrl() ?>" alt="<?=Yii::t('database', $drop->name)?>">
                                    </div>
                                    <?php $form = ActiveForm::begin(); ?>
                                    <input type="hidden" name="rate" value="<?=$userDrop->id?>"/>
                                    <button type="submit" class="btn battle_view_players_player2_select_rate_inventory_card_btn">
                                        <?=Yii::t('common', 'Ставка')?>
                                    </button>
                                    <?php ActiveForm::end(); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif;?>
            </div>
        </div>
    <?php endif; ?>
</div>