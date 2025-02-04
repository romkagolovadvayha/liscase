<?php

use yii\bootstrap5\Html;

/** @var \common\models\user\UserConfirmCode $userConfirmModel */

$this->title = Yii::t('common', 'Код активации');

?>

<section class="tasks">
    <h2 class="tasks__title">
        <?=Yii::t('common', 'Активация телеграм бота')?>
    </h2>
    <section class="page-stats__block-without-hover">
        <section>
            <header class="flex items-center justify-space-between mb-24 transition-all">
                <h4 class="flex items-center gap-x-12">
                    <?=Yii::t('common', "Код активации персонального Telegram-бота")?>
                </h4>
            </header>
            <div>
                <div class="relative mb-12 btn-clipboard"
                     style="max-width: 280px"
                     data-bs-toggle="tooltip"
                     data-bs-placement="right"
                     data-bs-title="<?=Yii::t('common', 'Скопировать код')?>"
                     data-clipboard-text="<?=$userConfirmModel->code?>"
                     data-message="<?=Yii::t('common', 'Код скопирован в буфер обмена!')?>">
                    <input class="search search_pay" value="<?=$userConfirmModel->code?>" readonly="">
                    <span class="icons icons_16px fas fa-copy"></span>
                </div>
                <?php
                    $botLink = Html::a('@' . Yii::$app->settings->get('tgbot_login'), 'https://t.me/' . Yii::$app->settings->get('tgbot_login'), ['target' => '_blank']);
                ?>
                <p class="mt-24 p1 text-left">
                    <?= Yii::t('common', 'Скопируйте и вставьте это код в {botLink}', [
                        'botLink' => $botLink
                    ])?>
                </p>
            </div>
        </section>
    </section>
</section>