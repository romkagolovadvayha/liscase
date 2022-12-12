<?php

use yii\web\View;
use common\models\battle\Battle;

/** @var View $this */

$this->title = Yii::t('common', "Архив сражений на скины CSGO");

$this->registerJs(<<<JS
    var battleStatus = 3;
JS, View::POS_BEGIN);
\frontend\assets\BattleAsset::register($this);
?>

<main id="main" role="main" class="mt-5">
    <div class="container">
        <?= $this->render('@frontend/views/layouts/_battle_menu'); ?>
        <div class="tab-content mt-2">
            <div class="battle_rows_wrapper">
                <?=$this->render('_battle_list', [
                    'status' => Battle::STATUS_FINISH,
                ])?>
            </div>
        </div>
    </div>
</main>

