<?php

/** @var yii\web\View $this */
/** @var \common\models\box\Drop $drop */

use common\models\box\Box;
use yii\bootstrap5\ActiveForm;
use frontend\widgets\Alert;
use yii\widgets\Pjax;

?>


<div class="modal_form_product">
    <div class="modal_form_product_image">
        <img src="<?= $drop->imageOrig->getImagePubUrl() ?>" alt="<?= Yii::t('database', $drop->name) ?>" width="150px">
    </div>
    <?php if (Yii::$app->user->isGuest): ?>
        <div class="market_entity_card_alert">
            <div class="market_entity_card_alert_title"><?=Yii::t('common', 'ВЫ НЕ АВТОРИЗОВАНЫ!')?></div>
            <div class="market_entity_card_alert_text"><?=Yii::t('common', 'Для покупки необходимо пройти авторизацию')?></div>
        </div>
        <div class="market_entity_card_actions">
            <a href="/auth/oauth?authclient=steam" class="market_entity_card_actions_btn btn_steam" title="Авторизация через Steam">
                <i class="fab fa-steam"></i> <span><?=Yii::t('common', 'Войти через Steam')?></span>
            </a>
        </div>
    <?php else: ?>
        <div class="modal_form_product_description"><?=Yii::t('database', $drop->description)?></div>
        <table class="table">
            <tr>
                <td><?=Yii::t('common', 'Количество')?></td>
                <td>x<?=$drop->count?></td>
            </tr>
            <tr>
                <td><?=Yii::t('common', 'Стоимость')?></td>
                <td><?=$drop->getRealPrice()?> RUB</td>
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
</div>
<div class="page_preloader" id="product-loader"></div>
