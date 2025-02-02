<?php

use yii\web\View;
use frontend\widgets\Alert;
use yii\web\NotFoundHttpException;

/** @var View $this */

$user = Yii::$app->user->identity;
$this->title = Yii::t('common', "Реферальная система");

if (!Yii::$app->settings->get('section_referral')) {
    throw new NotFoundHttpException(Yii::t('common', "Страница не найдена"));
}

?>

<?= Alert::widget() ?>
<section class="tasks">
    <h2 class="tasks__title">
        <?=Yii::t('common', 'Реферальная система')?>
    </h2>
    <section class="page-stats__block-without-hover">
        <section>
            <header class="flex items-center justify-space-between mb-24 transition-all">
                <h4 class="flex items-center gap-x-12">
                    <?=Yii::t('common', "Условия партнерской программы")?>
                </h4>
            </header>
            <div class="page-stats__categories">
                <div class="page-stats__category category">
                    <h5 class="category__count-and-img">
                        <span><?=Yii::$app->settings->get('referral_percent')?>%</span>
                        <!--                                <img src="/uploads/drop/312_f1bbbd27a6a2da9e0f82b83ae1ac284a.png" alt="Серная руда" class="w-64 h-64 object-contain">-->
                    </h5>
                    <p class="category__title"><?=Yii::t('common', "Ваш процент с донатов вам на карту")?></p>
                </div>
                <div class="page-stats__category category">
                    <h5 class="category__count-and-img">
                        <span><?=Yii::$app->settings->get('referral_bonus')?> <span class="icons icons_16px icons_16px_coin" style="vertical-align: middle;margin-top: -3px;"></span></span>
                        <!--                                <img src="/uploads/drop/312_f1bbbd27a6a2da9e0f82b83ae1ac284a.png" alt="Серная руда" class="w-64 h-64 object-contain">-->
                    </h5>
                    <p class="category__title"><?=Yii::t('common', "Бонус на счет магазина")?></p>
                </div>
                <?php if (Yii::$app->settings->get('referral_skin')): ?>
                    <div class="page-stats__category category">
                        <h5 class="category__count-and-img">
                            <span><?=Yii::t('common', "от")?> <?=Yii::$app->settings->get('referral_minSum')?> <?=Yii::t('common', "до")?> <?=Yii::$app->settings->get('referral_maxSum')?> <span class="icons icons_16px icons_16px_coin" style="vertical-align: middle;margin-top: -3px;"></span></span>
                            <!--                                <img src="/uploads/drop/312_f1bbbd27a6a2da9e0f82b83ae1ac284a.png" alt="Серная руда" class="w-64 h-64 object-contain">-->
                        </h5>
                        <p class="category__title"><?=Yii::t('common', "Скин за каждого приглашенного")?></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <section class="mt-40">
            <header class="flex items-center justify-space-between mb-24 transition-all">
                <h4 class="flex items-center gap-x-12">
                    <?=Yii::t('common', "Приглашенным считается игрок, который:")?>
                </h4>
            </header>
            <table class="kv-grid-table table table-bordered table-striped kv-table-wrap">
                <tbody>
                <tr>
                    <td><?=Yii::t('common', "Пребывал на одном из наших серверов не менее 1 часа.")?></td>
                </tr>
                <tr>
                    <td><?=Yii::t('common', "Зарегистрировался на сайте до того, как зашел на сервер. Игроки, которые зарегистрировались на сайте после того, как зашли на сервер, не считаются приглашенными.")?></td>
                </tr>
                </tbody>
            </table>
        </section>
        <section class="mt-40">
            <header class="flex items-center justify-space-between mb-24 transition-all">
                <h4 class="flex items-center gap-x-12">
                    <?=Yii::t('common', "Получение бонусов")?>
                </h4>
            </header>
            <table class="kv-grid-table table table-bordered table-striped kv-table-wrap">
                <tbody>
                <tr>
                    <td><?=Yii::t('common', "Бонус в размере {PARAM_BONUS_COUNT} {PARAM_ICON} за каждого приглашенного игрока.", [
                            'PARAM_BONUS_COUNT' => Yii::$app->settings->get('referral_bonus'),
                            'PARAM_ICON' => '<span class="icons icons_16px icons_16px_coin" style="vertical-align: middle;margin-top: -3px;"></span>'
                        ])?></td>
                </tr>
                <tr>
                    <td><?=Yii::t('common', "Скин на случайном основе с ценой от {PARAM_MIN_SUM} до {PARAM_MAX_SUM} {PARAM_ICON} за каждого приглашенного игрока.", [
                            'PARAM_MIN_SUM' => Yii::$app->settings->get('referral_minSum'),
                            'PARAM_MAX_SUM' => Yii::$app->settings->get('referral_maxSum'),
                            'PARAM_ICON' => '<span class="icons icons_16px icons_16px_coin" style="vertical-align: middle;margin-top: -3px;"></span>'
                        ])?></td>
                </tr>
                <tr>
                    <td><?=Yii::t('common', "Партнер получает {PARAM_PERCENT}% от суммы донатов, сделанных приглашенными игроками, который зарегистрировался по вашей персональной ссылке.", [
                            'PARAM_PERCENT' => Yii::$app->settings->get('referral_percent'),
                        ])?></td>
                </tr>
                <tr>
                    <td><?=Yii::t('common', "Все бонусы, включая денежные и скин, выплачиваются и выдаются через личный кабинет игрока в разделе Профиль » Выдача скинов.")?></td>
                </tr>
                </tbody>
            </table>
        </section>
        <section class="mt-40">
            <header class="flex items-center justify-space-between mb-24 transition-all">
                <h4 class="flex items-center gap-x-12">
                    <?=Yii::t('common', "Персональная ссылка")?>
                </h4>
            </header>
            <table class="kv-grid-table table table-bordered table-striped kv-table-wrap">
                <tbody>
                <tr>
                    <td><?=Yii::t('common', "Для приглашения новых игроков вам будет предоставлена персональная ссылка.")?></td>
                </tr>
                <tr>
                    <td><?=Yii::t('common', "Каждый приглашенный игрок должен зарегистрироваться через эту ссылку, чтобы он был зачислен как ваш приглашенный.")?></td>
                </tr>
                </tbody>
            </table>
        </section>
    </section>
</section>