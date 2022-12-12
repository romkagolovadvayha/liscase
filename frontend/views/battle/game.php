<?php

use yii\web\View;
use common\models\battle\Battle;
use frontend\widgets\Alert;

/** @var View $this */
/** @var Battle $battle */

$this->title = Yii::t('common', "Сражение против игрока") . " " . $battle->player1->userProfile->name;

?>

<main id="main" role="main" class="mt-5">
    <div class="container">
        <?= Alert::widget() ?>
        <div class="battle_view">
            <div class="battle_view_players">
                <?= $this->render('_player1_card', [
                        'battle' => $battle
                ]); ?>
                <div class="battle_view_players_middle">
                    <div class="battle_view_players_middle_separator">VS</div>
                </div>
                <?= $this->render('_player2_card', [
                    'battle' => $battle
                ]); ?>
            </div>
        </div>
    </div>
</main>
