<?php

/** @var yii\web\View $this */
/** @var Sets $sets */

use common\models\box\Sets;
use yii\bootstrap5\ActiveForm;
use frontend\widgets\Alert;
use yii\widgets\Pjax;

?>


<div class="modal_form_product">
    <div class="modal_form_products">
        <?php foreach ($sets->setsDrop as $setsDrop): ?>
            <?php $blocked = !empty($setsDrop->drop->blocked_at) && strtotime($setsDrop->drop->blocked_at) > time(); ?>
            <div class="modal_form_products_item">
                <div class="modal_form_products_item_name">
                    <?= Yii::t('database', $setsDrop->drop->name) ?>
                </div>
                <div class="modal_form_products_item_image">
                    <img src="<?= $setsDrop->drop->imageOrig->getImagePubUrl() ?>" alt="<?= Yii::t('database', $setsDrop->drop->name) ?>" width="100px">
                </div>
                <div class="modal_form_products_item_count">x<?=$setsDrop->count?></div>
                <?php if ($blocked): ?>
                    <div class="modal_form_products_item_blocked_wrap">
                        <div class="modal_form_products_item_blocked_title"><?=Yii::t('common', 'Вайп блок')?></div>
                        <div class="modal_form_products_item_blocked_timer blocked_products_timer" data-time="<?=strtotime($setsDrop->drop->blocked_at)?>"><?=$setsDrop->drop->blocked_at?></div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if (Yii::$app->user->isGuest): ?>
        <div class="market_entity_card_alert">
            <div class="market_entity_card_alert_title"><?=Yii::t('common', 'ВЫ НЕ АВТОРИЗОВАНЫ!')?></div>
            <div class="market_entity_card_alert_text"><?=Yii::t('common', 'Для покупки необходимо пройти авторизацию')?></div>
        </div>
        <div class="market_entity_card_actions">
            <a href="/auth/oauth?authclient=steam" class="market_entity_card_actions_btn btn_steam" title="Авторизация через Steam">
                <span><?=Yii::t('common', 'Войти через Steam')?></span>
            </a>
        </div>
    <?php else: ?>
        <div class="modal_form_product_description"><?=Yii::t('database', $sets->description)?></div>
        <table class="table">
            <tr>
                <td><?=Yii::t('common', 'Стоимость')?></td>
                <td><?=$sets->getRealPrice()?> RUB</td>
            </tr>
        </table>
        <div class="productModalGiveText"><?=Yii::t('common', 'Чтобы получить, введите /store в чат')?></div>
        <?php Pjax::begin(
            [
                'id'              => 'buy-container-pjax',
                'enablePushState' => false
            ]
        ); ?>
        <?= Alert::widget() ?>
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
        <input type="hidden" name="buy" value="1"/>
        <div class="modal_form_product_buttons">
            <button type="button" class="btn cancel" data-bs-dismiss="modal"><?=Yii::t('common', 'Закрыть')?></button>
            <button type="submit" class="btn" id="buy_product"><?=Yii::t('common', 'Оплатить')?></button>
        </div>
        <?php ActiveForm::end(); ?>
        <?php Pjax::end(); ?>
    <?php endif; ?>
    <div class="page_preloader" id="product-loader"></div>
</div>
<script>
    var blocked_products = $('.blocked_products_timer');
    for (var i = 0; i < blocked_products.length; i++) {
        var dateTime = $(blocked_products[i]).attr('data-time');
        var left = moment.unix(dateTime);
        $(blocked_products[i]).html(left.locale('<?=substr(Yii::$app->language, 0, 2)?>').fromNow());
    }
</script>