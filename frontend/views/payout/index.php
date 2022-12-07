<?php

use yii\web\View;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Вывод") . " - {$user->userProfile->name}";
?>

<main id="main" role="main" class="mt-5">
    <div class="container">
        <?= $this->render('@frontend/views/layouts/_inventory_menu'); ?>
        <div class="tab-content mt-2">
            <p>
                <?= Yii::t('common', 'Данная таблица отражает полную историю ваших начислений по партнёрской программе') ?>
            </p>
        </div>
    </div>
</main>
