<?php

/** @var yii\web\View $this */
/** @var Sets $sets */

use common\models\box\Sets;
use yii\bootstrap5\ActiveForm;
use frontend\widgets\Alert;
use yii\widgets\Pjax;

?>
<div class="grid gap-y-24 px-24 mb-24">
    <div class="relative z-1 grid gap-y-16">
        <div class="inventory__list mb-10">
            <?php foreach ($sets->setsDrop as $setsDrop): ?>
                <?php $blocked = !empty($setsDrop->drop->blocked_at) && strtotime($setsDrop->drop->blocked_at) > time(); ?>
                <div class="inventory"<?=$blocked ? ' aria-disabled="true"' : ''?>>
                    <p class="inventory__boost">x<?=$setsDrop->count?></p>
                    <img class="inventory__image" src="<?= $setsDrop->drop->imageOrig->getImagePubUrl() ?>" alt="<?= Yii::t('database', $setsDrop->drop->name) ?>">
                    <p class="inventory__title"><?= Yii::t('database', $setsDrop->drop->name) ?></p>
                </div>
                    <!--<?php if ($blocked): ?>
                        <div class="modal_form_products_item_blocked_wrap">
                            <div class="modal_form_products_item_blocked_title"><?=Yii::t('common', 'Вайп блок')?></div>
                            <div class="modal_form_products_item_blocked_timer blocked_products_timer" data-time="<?=strtotime($setsDrop->drop->blocked_at)?>"><?=$setsDrop->drop->blocked_at?></div>
                        </div>
                    <?php endif; ?>-->
            <?php endforeach; ?>
        </div>

        <h2 class="mb-8">
            <?=$sets->getRealPrice()?>
            <span class="icons icons_16px icons_16px_coin"></span>
        </h2>
        <p class="p3 text-text-teritiary">
            <?=Yii::t('database', $sets->description)?>
        </p>

        <p class="p2 p-12 bg-background-teritiary rounded-8 flex items-center gap-x-12">
            <span class="icons icons_24px icons_24px_info"></span>
            <span>
                <?php if (!Yii::$app->params['basketSite']): ?>
                    <?=Yii::t('common', 'Чтобы получить, введите /store в чат')?>
                <?php else: ?>
                    <?=Yii::t('common', 'Чтобы получить, перейдите на эту страницу')?> <a href="/store" target="_blank"><?=Yii::$app->params['domain']?>/store</a>
                <?php endif; ?>
            </span>
        </p>
    </div>
</div>
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
<footer class="px-24 pb-24">
    <?= Alert::widget() ?>
    <?php if (Yii::$app->user->isGuest): ?>
        <a href="/auth/oauth?authclient=steam" class="market_entity_card_actions_btn btn_steam" title="Авторизация через Steam">
            <i class="fab fa-steam"></i> <span><?=Yii::t('common', 'Войти через Steam')?></span>
        </a>
    <?php else: ?>
        <?= Alert::widget() ?>
        <input type="hidden" class="modal_form_product_buy" name="buy" value="1"/>
        <div class="modal_form_product_buttons">
            <button type="submit" id="buy_product" class="button-primary w-full">
                <span class="button__text"><?=Yii::t('common', 'Оплатить')?></span>
            </button>
        </div>
    <?php endif; ?>
</footer>
<?php ActiveForm::end(); ?>
<?php Pjax::end(); ?>
<div class="modal_preloader" id="product-loader"></div>

<script>
    var blocked_products = $('.blocked_products_timer');
    for (var i = 0; i < blocked_products.length; i++) {
        var dateTime = $(blocked_products[i]).attr('data-time');
        var left = moment.unix(dateTime);
        $(blocked_products[i]).html(left.locale('<?=substr(Yii::$app->language, 0, 2)?>').fromNow());
    }
</script>