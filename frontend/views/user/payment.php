<?php

use yii\web\View;

/** @var View $this */

$user        = Yii::$app->user->identity;
$this->title = Yii::t('common', "Пополнения баланса");
?>

<main id="main" role="main" class="mt-5">
    <div class="container">
        <?=Yii::t('common', "Выберите удобный для вас способ пополнения баланса")?>
    </div>
</main>
