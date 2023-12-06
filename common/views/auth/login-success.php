<?php

/** @var  \yii\web\View $this */

$this->title = Yii::t('common', 'Добро пожаловать');

$this->registerJs('
    setTimeout(function () {
        document.location.href = "' . $url . '";
    }, 2000);
');
?>

<main id="content">
    <div class="login_group">
        <div class="logo_login">
            <img src="/images/new_logo.svg" alt=""/>
        </div>
        <div class="login_text">
            <?= Yii::t('common', 'Добро пожаловать!'); ?>
        </div>
    </div>
</main>