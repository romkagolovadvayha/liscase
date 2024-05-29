<?php

/** @var yii\web\View $this */
/** @var Select $model */

use common\models\box\Select;
use yii\bootstrap5\ActiveForm;
use frontend\widgets\Alert;
use yii\widgets\Pjax;
use yii\bootstrap5\Html;

?>

<div class="modal_form_product">
    <div class="modal_form_product_image">
        <img src="<?= $model->imageOrig->getImagePubUrl() ?>" alt="<?= Yii::t('database', $model->name) ?>" width="130px">
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
        <div class="modal_form_product_description"><?=$model->description?></div>
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
        <div class="modal_form_product_select">
            <?= $form->field($model, 'drop_id',
                             [
                                 'errorOptions'   => [
                                     'encode' => false,
                                     'class'  => 'help-block',
                                 ],
                             ])
                     ->radioList($model->selectDrop, [
                         'item' => function ($index, \common\models\box\SelectDrop $label, $name, $checked, $value) use ($model) {
                             $id = 'option_' . $label->drop->id . '_' . $index;
                             $return = Html::radio($name, $label->drop_id == $model->drop_id, [
                                 'id'    => $id,
                                 'value' => $label->drop->id,
                                 'class' => 'modal_form_product_select_item_radio',
                             ]);
                             $img = Html::img($label->drop->imageOrig->getImagePubUrl(), [
                                 'class' => 'modal_form_product_select_item_img',
                                 'title' => $label->drop->name,
                             ]);
                             $imgWrap = Html::tag('div', $img, [
                                 'class' => 'modal_form_product_select_item_img_wrap',
                             ]);
                             $price = Html::tag('div', $label->drop->getRealPrice() . " RUB", [
                                 'class' => 'modal_form_product_select_item_price',
                             ]);
                             $return .= Html::label($imgWrap, $id, [
                                 'class' => 'modal_form_product_select_item'
                             ]);
                             return $return;
                         },
                     ])
                     ->label(false); ?>
        </div>
        <table class="table">
            <tr>
                <td>Товар</td>
                <td><?=$model->drop->name?></td>
            </tr>
            <tr>
                <td>Стоимость</td>
                <td><?=$model->drop->getRealPrice()?> RUB</td>
            </tr>
        </table>
        <div class="productModalGiveText">Чтобы получить, введите /store в чат</div>
        <input type="hidden" class="modal_form_product_buy" name="buy" value="1"/>
        <div class="modal_form_product_buttons">
            <button type="button" class="btn cancel" data-bs-dismiss="modal"><?=Yii::t('common', 'Закрыть')?></button>
            <button type="submit" class="btn" id="buy_product"><?=Yii::t('common', 'Оплатить')?></button>
        </div>
        <?php ActiveForm::end(); ?>
        <?php Pjax::end(); ?>
    <?php endif; ?>
    <div class="page_preloader" id="product-loader"></div>
</div>
