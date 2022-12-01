<?php

use yii\web\View;
use common\models\payment\Payment;

/** @var View $this */
//payments__payment-btn--active
$user        = Yii::$app->user->identity;
$this->title = Yii::t('common', "Пополнения баланса");
?>

<main id="main" role="main" class="mt-5">
    <div class="container text-center">
        <h1>ПОПОЛНЕНИЕ БАЛАНСА</h1>
        <?= Yii::t('common', "Выберите удобный для вас способ пополнения баланса") ?>
        <div class="payments">
            <ul class="payments-list">
                <?php foreach (Payment::getIconTypeList() as $id => $icon): ?>
                    <li class="payments__payment" data-id="<?=$id?>">
                        <button class="payments__payment-btn">
                            <img class="payments__payment-icon" src="<?=$icon?>" alt="">
                        </button>
                    </li>
                <?php endforeach; ?>

            </ul>
        </div>
    </div>
</main>
