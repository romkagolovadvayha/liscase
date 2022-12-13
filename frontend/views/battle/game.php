<?php

use yii\web\View;
use common\models\battle\Battle;
use frontend\widgets\Alert;

/** @var View $this */
/** @var Battle $battle */

$this->title = Yii::t('common', "Сражение против игрока") . " " . $battle->player1->userProfile->name;

$this->registerJs(<<<JS
    var data = [];
JS
    , View::POS_BEGIN);

if (!empty($battle->player_winner_user_id)) {
    $winnerNumber = 1;
    if ($battle->player_winner_user_id === $battle->player2_user_id) {
        $winnerNumber = 2;
    }
    $this->registerJs(
        <<<JS
    $(window).ready(function(){
        spinner.spin({$winnerNumber});
    });
JS, View::POS_END
    );
}

\frontend\assets\BattleGameAsset::register($this);
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
                    <div class="battle_view_players_middle_roulete">
                        <div class="game_roulette">
                            <div class="spinner"></div>
                            <div class="shadow"></div>
                            <div class="markers">
                                <div class="triangle">

                                </div>
                            </div>
<!--                            <div class="button">-->
<!--                                <span>SPIN</span>-->
<!--                            </div>-->
                        </div>
                    </div>
                </div>
                <?= $this->render('_player2_card', [
                    'battle' => $battle
                ]); ?>
            </div>
        </div>
    </div>
</main>
