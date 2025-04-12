<?php

/** @var yii\web\View $this */
/** @var \common\models\box\Drop $drop */

use common\models\box\Box;
use yii\bootstrap5\ActiveForm;
use frontend\widgets\Alert;
use yii\widgets\Pjax;

?>
<div class="grid gap-y-24 px-24 mb-24">
    <figure class="mb-5 flex items-center justify-center w-full relative">
        <img src="/images/design/modal/light.png" alt="" class="absolute">
        <img src="<?= $drop->imageOrig->getImagePubUrl() ?>" alt="<?= Yii::t('database', $drop->name) ?>" class="w-180 h-180 relative z-1">
    </figure>

    <div class="relative z-1 grid gap-y-16">
        <h2 class="mb-8">
            <?=$drop->getRealPrice()?>
            <span class="icons icons_16px icons_16px_coin"></span>
        </h2>
        <p class="p3 text-text-teritiary">
            <?=Yii::t('database', $drop->description)?>
        </p>
        <p class="p2 text-text-secondary"><?=Yii::t('common', 'Количество')?>: х<?=$drop->count?></p>
        <p class="p2 p-12 bg-background-teritiary rounded-8 flex items-center gap-x-12">
            <span class="icons icons_24px icons_24px_info"></span>
            <span>
                <?php if (!Yii::$app->settings->get('site_basketSite')): ?>
                    <?=Yii::t('common', 'Чтобы получить, введите /store в чат')?>
                <?php else: ?>
                    <?=Yii::t('common', 'Чтобы получить, перейдите на эту страницу')?> <a href="/store" target="_blank"><?=Yii::$app->settings->get('site_domain')?>/store</a>
                <?php endif; ?>
            </span>
<!--            <span>Чтобы получить, введите <span class="text-link-color-default command_to_bot cursor-pointer" data-bs-toggle="tooltip" data-bs-title="Скопировать команду">/store</span> в чат</span>-->
        </p>
    </div>
</div>
<footer class="px-24 pb-24">
    <?php if (Yii::$app->user->isGuest): ?>
        <button class="button-danger w-full">
            <a href="/auth/oauth?authclient=steam" class="button__text" title="Авторизация через Steam">
                <i class="fab fa-steam"></i> <span><?=Yii::t('common', 'Войти через Steam')?></span>
            </a>
        </button>
    <?php else: ?>
        <?php Pjax::begin(
            [
                'id'              => 'buy-container-pjax',
                'enablePushState' => false
            ]
        ); ?>
        <?php $form = ActiveForm::begin(
            [
                'enableClientValidation' => false,
                'enableAjaxValidation'   => false,
                'id'                     => 'buy-container',
                'options'                => [
                    'data-pjax' => 1,
                ],
            ]
        ); ?>
        <?= Alert::widget() ?>
        <input type="hidden" name="buy" value="1"/>
        <div class="modal_form_product_buttons">
            <button class="button-primary w-full" id="buy_product" type="submit">
                <span class="button__text"><?=Yii::t('common', 'Оплатить')?></span>
            </button>
        </div>
        <?php ActiveForm::end(); ?>
        <?php Pjax::end(); ?>
    <?php endif; ?>
</footer>
<div class="modal_preloader" id="product-loader"></div>