<?php

use yii\web\View;
use common\models\battle\Battle;
use frontend\widgets\Alert;

/** @var View $this */

$this->title = Yii::t('common', "Сражения на скины CSGO");
//$jwtToken = null;
//if (!Yii::$app->user->isGuest) {
//    $jwtToken = Yii::$app->user->identity->getJwtToken();
//}
//$this->registerJs(<<<JS
//    var session_id = '{$jwtToken}';
//JS
//    , View::POS_BEGIN);
//

$this->registerJs(<<<JS
    var battleStatus = 2;
JS, View::POS_BEGIN);
\frontend\assets\BattleAsset::register($this);

?>

<main id="main" role="main" class="mt-5">
    <div class="container">
        <?= $this->render('@frontend/views/layouts/_battle_menu'); ?>
        <div class="tab-content mt-2">
            <?= Alert::widget() ?>
            <div class="battle_rows_wrapper">
                <?=$this->render('_battle_list', [
                    'status' => Battle::STATUS_WAIT_PLAYER,
                ])?>
            </div>
        </div>
    </div>
</main>

